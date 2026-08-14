<?php

namespace App\Servicios;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoNotificacion;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de las reservas (HU-07 / HU-09).
 *
 * Dos invariantes que conviene no perder de vista:
 *
 *  1. El monto NUNCA llega del cliente: se lee del inmueble dentro de la misma
 *     transacción, para que nadie pueda manipular lo que va a pagar.
 *  2. Al crear la reserva el inmueble NO cambia de estado (RN-11 / CU-08):
 *     sigue Disponible con bloqueo lógico y solo pasa a Reservado cuando el
 *     pago queda confirmado.
 */
class ReservaService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /**
     * Crea una reserva sobre un inmueble disponible.
     *
     * @throws ValidationException si el inmueble no admite la reserva
     */
    public function solicitar(
        Inmueble $inmueble,
        User $cliente,
        ?ModalidadInmueble $modalidad,
        ?string $notas,
    ): Reserva {
        $reserva = DB::transaction(function () use ($inmueble, $cliente, $modalidad, $notas) {
            // Bloqueo pesimista: evita que dos clientes reserven a la vez
            $bloqueado = Inmueble::whereKey($inmueble->getKey())->lockForUpdate()->firstOrFail();

            $this->asegurarQueEsReservable($bloqueado);

            $reserva = Reserva::create([
                'codigo_reserva' => Reserva::generarCodigo(),
                'inmueble_id' => $bloqueado->id,
                'usuario_id' => $cliente->id,
                'monto_reserva' => $this->montoDeReserva($bloqueado, $modalidad),
                'estado' => EstadoReserva::PendientePago,
                'expira_en' => now()->addHours(Reserva::HORAS_PARA_PAGAR),
                'notas_cliente' => $notas,
            ]);

            HistorialReserva::registrar(
                $reserva,
                null,
                EstadoReserva::PendientePago->value,
                'Reserva creada por el cliente.',
                $cliente->id,
                request()?->ip(),
            );

            return $reserva;
        });

        $this->notificaciones->paraUsuario(
            $cliente,
            'Reserva registrada',
            "Tu reserva {$reserva->codigo_reserva} quedó registrada. Tienes ".Reserva::HORAS_PARA_PAGAR.' horas para completar el pago.',
            TipoNotificacion::Info,
            'reserva',
            $reserva->id,
            conCorreo: true,
        );

        $this->notificaciones->paraStaff(
            'Nueva reserva',
            "{$cliente->nombre} reservó «{$inmueble->titulo}» ({$reserva->codigo_reserva}).",
            TipoNotificacion::Info,
            'reserva',
            $reserva->id,
        );

        return $reserva;
    }

    // Confirma la reserva tras aprobarse el pago: el inmueble pasa a Reservado (HU-09.1)
    public function confirmar(Reserva $reserva, ?User $autor = null): void
    {
        $this->transicionar(
            $reserva,
            EstadoReserva::Confirmada,
            'Pago aprobado: la reserva queda confirmada.',
            $autor,
        );

        $reserva->inmueble->update(['estado' => EstadoInmueble::Reservado]);

        $this->notificaciones->paraUsuario(
            $reserva->cliente,
            'Reserva confirmada',
            "Tu reserva {$reserva->codigo_reserva} está confirmada. Nos pondremos en contacto para los siguientes pasos.",
            TipoNotificacion::Exito,
            'reserva',
            $reserva->id,
            conCorreo: true,
        );
    }

    // Cancelación por el cliente o por la administración; libera el inmueble
    public function cancelar(Reserva $reserva, User $autor, ?string $motivo = null): void
    {
        if ($reserva->estado->esFinal()) {
            throw ValidationException::withMessages([
                'estado' => 'Esta reserva ya está cerrada y no admite cambios.',
            ]);
        }

        $this->transicionar(
            $reserva,
            EstadoReserva::Cancelada,
            $motivo ?: 'Reserva cancelada.',
            $autor,
        );

        $this->liberarInmueble($reserva);

        $this->notificaciones->paraUsuario(
            $reserva->cliente,
            'Reserva cancelada',
            "Tu reserva {$reserva->codigo_reserva} fue cancelada.".($motivo ? " Motivo: {$motivo}" : ''),
            TipoNotificacion::Aviso,
            'reserva',
            $reserva->id,
            conCorreo: true,
        );
    }

    // Expiración automática por vencimiento del plazo de pago (HU-09.2)
    public function expirar(Reserva $reserva): void
    {
        $this->transicionar(
            $reserva,
            EstadoReserva::Expirada,
            'Expirada automáticamente: venció el plazo de pago.',
        );

        $this->liberarInmueble($reserva);

        $this->notificaciones->paraUsuario(
            $reserva->cliente,
            'Reserva expirada',
            "Tu reserva {$reserva->codigo_reserva} expiró porque venció el plazo de pago. El inmueble vuelve a estar disponible.",
            TipoNotificacion::Aviso,
            'reserva',
            $reserva->id,
        );
    }

    // ----------------------------------------------------------------
    // Apoyo
    // ----------------------------------------------------------------

    /**
     * Aplica un cambio de estado y lo deja registrado en el historial.
     * Un `autor` nulo identifica los cambios originados por el sistema.
     */
    private function transicionar(Reserva $reserva, EstadoReserva $nuevo, string $comentario, ?User $autor = null): void
    {
        DB::transaction(function () use ($reserva, $nuevo, $comentario, $autor) {
            $anterior = $reserva->estado;
            $reserva->update(['estado' => $nuevo]);

            HistorialReserva::registrar($reserva, $anterior->value, $nuevo->value, $comentario, $autor?->id, request()?->ip());
        });
    }

    /**
     * Devuelve el inmueble al estado que le corresponde según sus datos
     * relacionales, en vez de forzarlo siempre a Disponible como hacía el
     * prototipo: si tiene otra reserva viva o un contrato, debe conservarlo.
     */
    private function liberarInmueble(Reserva $reserva): void
    {
        $inmueble = $reserva->inmueble->fresh();

        $inmueble->update(['estado' => $inmueble->estadoCalculado()]);
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueEsReservable(Inmueble $inmueble): void
    {
        if ($inmueble->estado !== EstadoInmueble::Disponible) {
            throw ValidationException::withMessages([
                'inmueble' => 'Este inmueble ya no está disponible.',
            ]);
        }

        if ($inmueble->tieneSolicitudPendiente()) {
            throw ValidationException::withMessages([
                'inmueble' => 'Este inmueble ya tiene una reserva en proceso.',
            ]);
        }
    }

    /**
     * Precio real del inmueble según la modalidad reservada.
     *
     * @throws ValidationException
     */
    private function montoDeReserva(Inmueble $inmueble, ?ModalidadInmueble $modalidad): float
    {
        $elegida = $inmueble->modalidad === ModalidadInmueble::Ambos
            ? $modalidad
            : $inmueble->modalidad;

        if ($elegida === null || $elegida === ModalidadInmueble::Ambos) {
            throw ValidationException::withMessages([
                'modalidad' => 'Indica si deseas reservar en venta o en arriendo.',
            ]);
        }

        $monto = $elegida === ModalidadInmueble::Venta
            ? $inmueble->precio_venta
            : $inmueble->precio_arrendamiento;

        if ($monto === null || (float) $monto <= 0) {
            throw ValidationException::withMessages([
                'modalidad' => 'Este inmueble no tiene un precio configurado; contacta al administrador.',
            ]);
        }

        return (float) $monto;
    }
}
