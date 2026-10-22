<?php

namespace App\Servicios\Reportes;

use App\Enumerados\TipoReporte;
use Illuminate\Support\Collection;

/**
 * Reporte integral: junta en una sola tabla las cifras resumen de los demás
 * reportes (reservaciones, pagos, contratos y ventas) con el mismo filtro.
 */
class ReporteIntegral extends Reporte
{
    public function tipo(): TipoReporte
    {
        return TipoReporte::Integral;
    }

    public function columnas(): array
    {
        return ['Módulo', 'Indicador', 'Valor'];
    }

    // La columna «Valor» mezcla conteos e importes ya formateados: no se declara numérica
    // para que Excel no aplique formato de moneda a todos.
    public function columnasNumericas(): array
    {
        return [];
    }

    public function filas(): Collection
    {
        $fabrica = app(FabricaReportes::class);
        $filas = collect();

        foreach ([TipoReporte::Reservaciones, TipoReporte::Pagos, TipoReporte::Contratos, TipoReporte::Ventas] as $tipo) {
            foreach ($fabrica->crear($tipo, $this->filtro)->resumen() as $indicador => $valor) {
                $filas->push([$tipo->etiqueta(), $indicador, $valor]);
            }
        }

        return $filas;
    }

    public function resumen(): array
    {
        return [];
    }
}
