<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estados de un contrato de arriendo (HU-17).
 * Un contrato vigente mantiene el inmueble Ocupado; al vencer o rescindirse,
 * el inmueble vuelve a estar Disponible.
 */
enum EstadoContrato: string
{
    use ConValores;

    case Vigente = 'Vigente';
    case Vencido = 'Vencido';
    case Rescindido = 'Rescindido';

    public function etiqueta(): string
    {
        return $this->value;
    }

    public function claseBadge(): string
    {
        return match ($this) {
            self::Vigente => 'badge-confirmada',
            self::Vencido => 'badge-expirada',
            self::Rescindido => 'badge-cancelada',
        };
    }

    // Solo un contrato vigente ocupa el inmueble
    public function ocupaInmueble(): bool
    {
        return $this === self::Vigente;
    }
}
