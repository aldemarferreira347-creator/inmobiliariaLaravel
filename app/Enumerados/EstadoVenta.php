<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estados de una venta gestionada por el asesor (HU-14).
 * «En proceso» reserva el inmueble; «Cancelada» lo libera.
 */
enum EstadoVenta: string
{
    use ConValores;

    case EnProceso = 'En proceso';
    case Cerrada = 'Cerrada';
    case Cancelada = 'Cancelada';

    public function etiqueta(): string
    {
        return $this->value;
    }

    public function claseBadge(): string
    {
        return match ($this) {
            self::EnProceso => 'badge-pendiente-pago',
            self::Cerrada => 'badge-confirmada',
            self::Cancelada => 'badge-cancelada',
        };
    }

    public function bloqueaInmueble(): bool
    {
        return $this === self::EnProceso;
    }
}
