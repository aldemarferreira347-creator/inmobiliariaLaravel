<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;
use Carbon\CarbonImmutable;

/**
 * Periodo de las pestañas de los reportes.
 * El prototipo usaba «año» con tilde en la URL; aquí el valor es ASCII para
 * evitar problemas de codificación en los enlaces.
 */
enum PeriodoReporte: string
{
    use ConValores;

    case Semana = 'semana';
    case Mes = 'mes';
    case Anio = 'anio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Semana => 'Esta semana',
            self::Mes => 'Este mes',
            self::Anio => 'Este año',
        };
    }

    // Instante desde el que cuenta el periodo; el fin es siempre «ahora»
    public function inicio(): CarbonImmutable
    {
        $ahora = CarbonImmutable::now();

        return match ($this) {
            self::Semana => $ahora->startOfWeek(),
            self::Mes => $ahora->startOfMonth(),
            self::Anio => $ahora->startOfYear(),
        };
    }

    public static function porDefecto(): self
    {
        return self::Mes;
    }
}
