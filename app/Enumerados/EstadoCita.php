<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estados por los que pasa una cita de visita (HU-10 / HU-11 / HU-12 / HU-27).
 */
enum EstadoCita: string
{
    use ConValores;

    case Pendiente = 'Pendiente';
    case Asignada = 'Asignada';
    case Realizada = 'Realizada';
    case Cancelada = 'Cancelada';

    public function etiqueta(): string
    {
        return $this->value;
    }

    public function claseBadge(): string
    {
        return match ($this) {
            self::Pendiente => 'badge-pendiente-pago',
            self::Asignada => 'badge-pendiente-conf',
            self::Realizada => 'badge-confirmada',
            self::Cancelada => 'badge-cancelada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Pendiente => 'clock',
            self::Asignada => 'user',
            self::Realizada => 'circle-check',
            self::Cancelada => 'ban',
        };
    }

    // Estados terminales: ya no admiten ninguna transición
    public function esFinal(): bool
    {
        return in_array($this, [self::Realizada, self::Cancelada], true);
    }

    // El admin solo asigna/reasigna asesor mientras la cita sigue viva
    public function admiteAsignacion(): bool
    {
        return in_array($this, [self::Pendiente, self::Asignada], true);
    }

    // El cliente solo retira una visita que todavía no se realizó
    public function admiteCancelacion(): bool
    {
        return in_array($this, [self::Pendiente, self::Asignada], true);
    }
}
