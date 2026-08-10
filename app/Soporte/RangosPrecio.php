<?php

namespace App\Soporte;

/**
 * Escalones de precio de los desplegables del catálogo.
 * Están aquí para que el formulario del home y el del listado ofrezcan
 * exactamente las mismas opciones.
 */
final class RangosPrecio
{
    /** @var array<int, string> valor en pesos => etiqueta */
    public const TOPES = [
        500_000 => '$500.000',
        1_000_000 => '$1.000.000',
        2_000_000 => '$2.000.000',
        5_000_000 => '$5.000.000',
        100_000_000 => '$100.000.000',
        500_000_000 => '$500.000.000',
        1_000_000_000 => '$1.000.000.000',
    ];
}
