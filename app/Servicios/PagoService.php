<?php

namespace App\Servicios;

use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Enumerados\TipoNotificacion;
use App\Mail\ComprobantePago;
use App\Models\HistorialReserva;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registro y revisión de pagos (HU-23).
 *
 * Este servicio es agnóstico a cómo llega la confirmación del pago: hoy la
 * aprueba el administrador desde el panel, y cuando se integre una pasarela
 * será su driver quien invoque `aprobar()` / `rechazar()` sin tocar nada más.
 */
class PagoService
{
    public function __construct(
        private readonly ReservaService $reservas,
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * El cliente declara haber pagado su reserva; el pago queda en revisión.
     *
     * @throws ValidationException
     */
    public function registrar(Reserva $reserva, MetodoPago $metodo, ?string $referencia): Pago
    {
        $pago = $this->iniciar($reserva, $metodo, $referencia, "Pago registrado por el cliente ({$metodo->etiqueta()}).");

        $this->notificaciones->paraStaff(
            'Pago pendiente de revisión',
            "{$reserva->cliente->nombre} registró un pago de {$pago->monto_formateado} para la reserva {$reserva->codigo_reserva}.",
            TipoNotificacion::Info,
            'reserva',
            $reserva->id,
        );

        return $pago;
    }

    /**
     * Crea el pago en revisión y pasa la reserva a «En revisión», con la fila
     * de la reserva bloqueada: dos envíos simultáneos (doble clic) no pueden
     * crear dos pagos ni dos cobros.
     *
     * @throws ValidationException
     */
    public function iniciar(Reserva $reserva, MetodoPago $metodo, ?string $referencia, string $comentario): Pago
    {
        return DB::transaction(function () use ($reserva, $metodo, $referencia, $comentario) {
            if (! $reserva->releerBloqueado()->admiteNuevoPago()) {
                throw ValidationException::withMessages([
                    'reserva' => 'Esta reserva no admite registrar un pago en este momento.',
                ]);
            }

            $pago = Pago::create([
                'reserva_id' => $reserva->id,
                'metodo_pago' => $metodo,
                'monto' => $reserva->monto_reserva,
                'referencia' => $referencia,
                'estado' => EstadoPago::Procesando,
            ]);

            // La reserva pasa a «En revisión»: el plazo de 24 h deja de correr
            $anterior = $reserva->estado;
            $reserva->update(['estado' => EstadoReserva::ProcesandoPago]);

            HistorialReserva::registrar(
                $reserva,
                $anterior->value,
                EstadoReserva::ProcesandoPago->value,
                $comentario,
                $reserva->usuario_id,
                request()?->ip(),
            );

            return $pago;
        });
    }

    /**
     * Anula un intento que nunca llegó a cobrarse (datos de tarjeta inválidos,
     * HU-23.4): el pago se borra y la reserva vuelve a «Pendiente de pago».
     */
    public function descartar(Pago $pago, string $motivo): void
    {
        DB::transaction(function () use ($pago, $motivo) {
            $reserva = $pago->reserva->releerBloqueado();

            // Compare-and-swap: solo vuelve si seguía esperando este pago
            $cambiadas = Reserva::whereKey($reserva->id)
                ->where('estado', EstadoReserva::ProcesandoPago->value)
                ->update(['estado' => EstadoReserva::PendientePago->value]);

            Pago::whereKey($pago->id)->whereIn('estado', [EstadoPago::Pendiente->value, EstadoPago::Procesando->value])->delete();

            if ($cambiadas === 0) {
                return;
            }

            $reserva->refresh();

            HistorialReserva::registrar(
                $reserva,
                EstadoReserva::ProcesandoPago->value,
                EstadoReserva::PendientePago->value,
                $motivo,
                $reserva->usuario_id,
                request()?->ip(),
            );
        });
    }

    /**
     * Aprueba el pago y confirma la reserva (HU-23.1).
     *
     * Un `$revisor` nulo identifica una confirmación automática de la
     * pasarela (StripeCardService / StripeWebhookController), sin que medie
     * revisión humana.
     *
     * @throws ValidationException
     */
    public function aprobar(Pago $pago, ?User $revisor = null): void
    {
        $aplicado = DB::transaction(function () use ($pago, $revisor) {
            $this->bloquearParaRevision($pago);

            if (! $this->revisarSiSigueEnRevision($pago, [
                'estado' => EstadoPago::Pagado->value,
                'revisado_por' => $revisor?->id,
                'revisado_en' => now(),
                'motivo_rechazo' => null,
            ])) {
                return false;
            }

            $this->reservas->confirmar($pago->reserva, $revisor);

            return true;
        });

        // Otra petición se adelantó: no se repite el comprobante ni el aviso
        if (! $aplicado) {
            return;
        }

        // HU-23.1: comprobante al correo del cliente. Un fallo de envío no
        // revierte una reserva que ya quedó confirmada.
        try {
            Mail::to($pago->reserva->cliente->email)->queue(new ComprobantePago($pago->fresh()));
        } catch (Throwable $e) {
            report($e);
        }

        $this->notificaciones->paraStaff(
            'Nuevo ingreso registrado',
            "Se confirmó un pago de {$pago->monto_formateado} de la reserva {$pago->reserva->codigo_reserva}.",
            TipoNotificacion::Exito,
            'reserva',
            $pago->reserva_id,
        );
    }

    /**
     * Rechaza el pago. La reserva vuelve a «Pendiente de pago» para que el
     * cliente pueda reintentarlo dentro del plazo (HU-23.2).
     *
     * @throws ValidationException
     */
    public function rechazar(Pago $pago, ?User $revisor, string $motivo): void
    {
        $aplicado = DB::transaction(function () use ($pago, $revisor, $motivo) {
            $this->bloquearParaRevision($pago);

            if (! $this->revisarSiSigueEnRevision($pago, [
                'estado' => EstadoPago::Rechazado->value,
                'revisado_por' => $revisor?->id,
                'revisado_en' => now(),
                'motivo_rechazo' => $motivo,
            ])) {
                return false;
            }

            $reserva = $pago->reserva;
            $anterior = $reserva->estado;

            // Compare-and-swap: la reserva solo vuelve a «Pendiente de pago» si
            // sigue esperando este pago (sin FK ni transacciones no basta la relectura)
            $cambiadas = Reserva::whereKey($reserva->id)
                ->where('estado', EstadoReserva::ProcesandoPago->value)
                ->update(['estado' => EstadoReserva::PendientePago->value]);

            if ($cambiadas === 0) {
                throw ValidationException::withMessages([
                    'pago' => 'La reserva ya no está esperando este pago.',
                ]);
            }

            $reserva->refresh();

            HistorialReserva::registrar(
                $reserva,
                $anterior->value,
                EstadoReserva::PendientePago->value,
                "Pago rechazado: {$motivo}",
                $revisor?->id,
                request()?->ip(),
            );

            return true;
        });

        if (! $aplicado) {
            return;
        }

        $this->notificaciones->paraUsuario(
            $pago->reserva->cliente,
            'Pago rechazado',
            "Tu pago de la reserva {$pago->reserva->codigo_reserva} fue rechazado. Motivo: {$motivo}. Puedes intentarlo de nuevo.",
            TipoNotificacion::Error,
            'reserva',
            $pago->reserva_id,
            conCorreo: true,
        );
    }

    /**
     * Compare-and-swap sobre el pago: solo cambia si en la base sigue en
     * revisión. Con MyISAM no hay transacciones ni bloqueos de fila, así que la
     * relectura de `bloquearParaRevision` no basta: dos peticiones podrían
     * pasarla a la vez. El UPDATE condicional lo gana una sola; la otra no
     * modifica nada (devuelve false) y no repite historial ni notificaciones.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function revisarSiSigueEnRevision(Pago $pago, array $cambios): bool
    {
        $cambiadas = Pago::whereKey($pago->id)
            ->whereIn('estado', [EstadoPago::Pendiente->value, EstadoPago::Procesando->value])
            ->update($cambios);

        if ($cambiadas === 0) {
            return false;
        }

        $pago->refresh();

        return true;
    }

    /**
     * Relee bloqueados la reserva y el pago (en ese orden, el mismo que usa
     * ReservaService) y comprueba que sigan esperando revisión: el webhook de
     * Stripe, otro administrador o una cancelación pueden haberse adelantado.
     *
     * @throws ValidationException
     */
    private function bloquearParaRevision(Pago $pago): void
    {
        $reserva = $pago->reserva->releerBloqueado();
        $pago->releerBloqueado();

        if (! $pago->estado->admiteRevision()) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya fue revisado.',
            ]);
        }

        if ($reserva->estado !== EstadoReserva::ProcesandoPago) {
            throw ValidationException::withMessages([
                'pago' => "La reserva ya no está esperando este pago (estado: {$reserva->estado->etiqueta()}).",
            ]);
        }
    }
}
