<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estados por los que pasa una reserva (HU-07 / HU-23).
 *
 * El estado del inmueble se deriva de aquí: mientras la reserva está en proceso
 * el inmueble sigue Disponible con bloqueo lógico, y solo pasa a Reservado
 * cuando el pago queda confirmado (RN-11 / CU-08).
 */
enum EstadoReserva: string
{
    use ConValores;

    case PendientePago = 'PENDIENTE_PAGO';
    case ProcesandoPago = 'PROCESANDO_PAGO';
    case Rechazada = 'RECHAZADA';
    case Confirmada = 'CONFIRMADA';
    case Cancelada = 'CANCELADA';
    case Expirada = 'EXPIRADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PendientePago => 'Pendiente de pago',
            self::ProcesandoPago => 'En revisión',
            self::Rechazada => 'Rechazada',
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
            self::Expirada => 'Expirada',
        };
    }

    public function claseBadge(): string
    {
        return match ($this) {
            self::PendientePago => 'badge-pendiente-pago',
            self::ProcesandoPago => 'badge-pendiente-conf',
            self::Rechazada => 'badge-rechazada',
            self::Confirmada => 'badge-confirmada',
            self::Cancelada => 'badge-cancelada',
            self::Expirada => 'badge-expirada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::PendientePago => 'clock',
            self::ProcesandoPago => 'refresh-cw',
            self::Rechazada => 'circle-x',
            self::Confirmada => 'circle-check',
            self::Cancelada => 'ban',
            self::Expirada => 'clock',
        };
    }

    /**
     * Reservas que bloquean el inmueble frente a nuevas solicitudes.
     * RECHAZADA sigue bloqueando hasta que expire o el cliente reintente el pago.
     */
    public static function bloqueanInmueble(): array
    {
        return [
            self::PendientePago->value,
            self::ProcesandoPago->value,
            self::Rechazada->value,
        ];
    }

    // Reservas vivas que impiden eliminar el inmueble o el usuario (HU-04.5)
    public static function activas(): array
    {
        return [
            self::PendientePago->value,
            self::ProcesandoPago->value,
            self::Confirmada->value,
        ];
    }

    // Estados terminales: ya no admiten ninguna transición
    public function esFinal(): bool
    {
        return in_array($this, [self::Cancelada, self::Expirada], true);
    }

    // El cliente solo puede registrar un pago mientras la reserva siga viva y sin confirmar
    public function admitePago(): bool
    {
        return in_array($this, [self::PendientePago, self::Rechazada], true);
    }

    // El cliente solo retira su propia reserva antes de que empiece la revisión del pago
    public function admiteCancelacionDelCliente(): bool
    {
        return $this === self::PendientePago;
    }
}
