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
        if (! $reserva->admiteNuevoPago()) {
            throw ValidationException::withMessages([
                'reserva' => 'Esta reserva no admite registrar un pago en este momento.',
            ]);
        }

        $pago = DB::transaction(function () use ($reserva, $metodo, $referencia) {
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
                "Pago registrado por el cliente ({$metodo->etiqueta()}).",
                $reserva->usuario_id,
                request()?->ip(),
            );

            return $pago;
        });

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
        $this->asegurarQueEsRevisable($pago);

        DB::transaction(function () use ($pago, $revisor) {
            $pago->update([
                'estado' => EstadoPago::Pagado,
                'revisado_por' => $revisor?->id,
                'revisado_en' => now(),
                'motivo_rechazo' => null,
            ]);

            $this->reservas->confirmar($pago->reserva, $revisor);
        });

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
        $this->asegurarQueEsRevisable($pago);

        DB::transaction(function () use ($pago, $revisor, $motivo) {
            $pago->update([
                'estado' => EstadoPago::Rechazado,
                'revisado_por' => $revisor?->id,
                'revisado_en' => now(),
                'motivo_rechazo' => $motivo,
            ]);

            $reserva = $pago->reserva;
            $anterior = $reserva->estado;
            $reserva->update(['estado' => EstadoReserva::PendientePago]);

            HistorialReserva::registrar(
                $reserva,
                $anterior->value,
                EstadoReserva::PendientePago->value,
                "Pago rechazado: {$motivo}",
                $revisor?->id,
                request()?->ip(),
            );
        });

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
     * @throws ValidationException
     */
    private function asegurarQueEsRevisable(Pago $pago): void
    {
        if (! $pago->estado->admiteRevision()) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya fue revisado.',
            ]);
        }
    }
}
