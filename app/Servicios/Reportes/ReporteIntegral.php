<?php

namespace App\Servicios\Reportes;

use App\Enumerados\TipoReporte;
use Illuminate\Support\Collection;

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

    public function columnasNumericas(): array
    {
        // We do not format it here since values can be both counts (int) and amounts (string formatted as money)
        // If we set it as numeric, Excel will format it with currency always.
        // It's better to keep it empty for a mixed column.
        return [];
    }

    public function filas(): Collection
    {
        $fabrica = app(FabricaReportes::class);
        $filas = collect();

        foreach ([TipoReporte::Reservaciones, TipoReporte::Pagos, TipoReporte::Contratos, TipoReporte::Ventas] as $tipo) {
            $reporte = $fabrica->crear($tipo, $this->filtro);
            
            foreach ($reporte->resumen() as $indicador => $valor) {
                // If it's a numeric string like "$1.000", keep it as string.
                $filas->push([
                    $tipo->etiqueta(),
                    $indicador,
                    $valor
                ]);
            }
        }

        return $filas;
    }

    public function resumen(): array
    {
        return [];
    }
}
