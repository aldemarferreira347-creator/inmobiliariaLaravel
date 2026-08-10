<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Modalidad comercial del inmueble.
 *
 * El prototipo PHP guardaba «ambos» como «venta» porque el CHECK de la base
 * de datos original no contemplaba ese valor. Aquí la columna es un enum real
 * de tres valores, así que ese ajuste queda eliminado.
 */
enum ModalidadInmueble: string
{
    use ConValores;

    case Venta = 'venta';
    case Arriendo = 'arriendo';
    case Ambos = 'ambos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Arriendo => 'Arriendo',
            self::Ambos => 'Ambos',
        };
    }

    public function exigePrecioVenta(): bool
    {
        return $this === self::Venta || $this === self::Ambos;
    }

    public function exigePrecioArriendo(): bool
    {
        return $this === self::Arriendo || $this === self::Ambos;
    }
}
