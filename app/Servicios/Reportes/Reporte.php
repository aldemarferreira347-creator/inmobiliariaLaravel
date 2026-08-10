<?php

namespace App\Servicios\Reportes;

use App\Enumerados\TipoReporte;
use Illuminate\Support\Collection;

/**
 * Contrato común de los reportes.
 *
 * Cada reporte se describe a sí mismo —sus columnas, sus filas y sus totales—
 * de modo que la pantalla, el Excel y el PDF se generan a partir de la misma
 * definición y no pueden quedar desalineados entre sí.
 */
abstract class Reporte
{
    public function __construct(protected readonly FiltroReporte $filtro) {}

    abstract public function tipo(): TipoReporte;

    /** @return array<int, string> encabezados de la tabla, en orden */
    abstract public function columnas(): array;

    /** @return Collection<int, array<int, string|int|float|null>> */
    abstract public function filas(): Collection;

    /** @return array<string, string|int> cifras destacadas del reporte */
    abstract public function resumen(): array;

    /** Índices de columna que contienen importes, para alinearlos y sumarlos */
    public function columnasNumericas(): array
    {
        return [];
    }

    public function titulo(): string
    {
        return $this->tipo()->etiqueta();
    }

    public function filtro(): FiltroReporte
    {
        return $this->filtro;
    }

    protected function formatearMoneda(int|float|string|null $valor): string
    {
        return '$'.number_format((float) $valor, 0, ',', '.');
    }
}
