<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\TipoReporte;
use App\Http\Controllers\Controller;
use App\Servicios\Reportes\ExportadorExcel;
use App\Servicios\Reportes\ExportadorPdf;
use App\Servicios\Reportes\FabricaReportes;
use App\Servicios\Reportes\FiltroReporte;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes del panel (HU-06 / HU-21).
 *
 * Cada tipo de reporte ({@see TipoReporte}) es su propia pantalla con sus
 * filtros y sus dos exportaciones, tal como pide la documentación. La
 * construcción del reporte se delega en {@see FabricaReportes}.
 */
class ReporteController extends Controller
{
    public function __construct(private readonly FabricaReportes $reportes) {}

    /** Panel de entrada con el resumen de cifras de cada tipo de reporte. */
    // Panel de entrada con las cifras de cada tipo de reporte
    public function index(Request $request): View
    {
        $filtro = FiltroReporte::desdePeticion($request);

        $resumenes = collect(TipoReporte::cases())
            ->filter(fn (TipoReporte $tipo) => $tipo !== TipoReporte::Integral)
            ->mapWithKeys(fn (TipoReporte $tipo) => [
                $tipo->value => $this->reportes->crear($tipo, $filtro)->resumen(),
            ]);

        return view('admin.reportes.index', compact('filtro', 'resumenes'));
    }

    /** Detalle de un tipo de reporte con sus filtros aplicados. */
    public function show(Request $request, TipoReporte $tipo): View
    {
        $filtro = FiltroReporte::desdePeticion($request);
        $reporte = $this->reportes->crear($tipo, $filtro);

        return view('admin.reportes.show', [
            'reporte' => $reporte,
            'filtro' => $filtro,
            'estados' => $this->reportes->estadosDe($tipo),
        ]);
    }

    /** Exporta el reporte filtrado a Excel. */
    public function excel(Request $request, TipoReporte $tipo, ExportadorExcel $exportador): StreamedResponse
    {
        return $exportador->exportar($this->reportes->crear($tipo, FiltroReporte::desdePeticion($request)));
    }

    /** Exporta el reporte filtrado a PDF. */
    public function pdf(Request $request, TipoReporte $tipo, ExportadorPdf $exportador): StreamedResponse
    {
        return $exportador->exportar($this->reportes->crear($tipo, FiltroReporte::desdePeticion($request)));
    }
}
