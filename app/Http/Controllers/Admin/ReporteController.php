<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\TipoReporte;
use App\Http\Controllers\Controller;
use App\Servicios\Reportes\ExportadorExcel;
use App\Servicios\Reportes\ExportadorPdf;
use App\Servicios\Reportes\FabricaReportes;
use App\Servicios\Reportes\FiltroReporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        try {
            $resumenes = collect(TipoReporte::cases())
                ->filter(fn (TipoReporte $tipo) => $tipo !== TipoReporte::Integral)
                ->mapWithKeys(fn (TipoReporte $tipo) => [
                    $tipo->value => $this->reportes->crear($tipo, $filtro)->resumen(),
                ]);
        } catch (Throwable $e) {
            return $this->errorDeGeneracion($e);
        }

        return view('admin.reportes.index', compact('filtro', 'resumenes'));
    }

    /** Detalle de un tipo de reporte con sus filtros aplicados. */
    public function show(Request $request, TipoReporte $tipo): View
    {
        $filtro = FiltroReporte::desdePeticion($request);

        try {
            $reporte = $this->reportes->crear($tipo, $filtro);
            // La vista consulta los datos al renderizar: se fuerza aquí para capturar el fallo (HU-06.3)
            $reporte->filas();
            $reporte->resumen();
        } catch (Throwable $e) {
            return $this->errorDeGeneracion($e);
        }

        return view('admin.reportes.show', [
            'reporte' => $reporte,
            'filtro' => $filtro,
            'estados' => $this->reportes->estadosDe($tipo),
        ]);
    }

    /**
     * HU-06.3: si el reporte no se puede construir se informa y se ofrece repetir
     * la misma petición. Responde 200 porque los hosting gratuitos sustituyen las
     * respuestas 5xx por su propia página.
     */
    private function errorDeGeneracion(Throwable $e): View
    {
        report($e);

        return view('admin.reportes.error', ['urlReintento' => request()->fullUrl()]);
    }

    /** Exporta el reporte filtrado a Excel. */
    public function excel(Request $request, TipoReporte $tipo, ExportadorExcel $exportador): StreamedResponse|RedirectResponse
    {
        $filtro = FiltroReporte::desdePeticion($request);

        return $this->rangoExcedido($tipo, $filtro) ?? $exportador->exportar($this->reportes->crear($tipo, $filtro));
    }

    /** Exporta el reporte filtrado a PDF. */
    public function pdf(Request $request, TipoReporte $tipo, ExportadorPdf $exportador): StreamedResponse|RedirectResponse
    {
        $filtro = FiltroReporte::desdePeticion($request);

        return $this->rangoExcedido($tipo, $filtro) ?? $exportador->exportar($this->reportes->crear($tipo, $filtro));
    }

    /**
     * Limita el rango exportable: con 2 000 filas el PDF llega a ~0,9–1,2 GB y
     * tarda 30 s, más de lo que aguanta el hosting gratuito. El reporte diario
     * admite 31 días y el resto 12 meses.
     */
    private function rangoExcedido(TipoReporte $tipo, FiltroReporte $filtro): ?RedirectResponse
    {
        $diario = $tipo === TipoReporte::Reservaciones;
        $limite = $diario ? $filtro->inicio()->copy()->addDays(31) : $filtro->inicio()->copy()->addMonths(12);

        if ($filtro->fin()->lte($limite)) {
            return null;
        }

        return back()->with([
            'mensaje' => $diario
                ? 'El rango a exportar no puede superar 31 días. Acorte las fechas e intente de nuevo.'
                : 'El rango a exportar no puede superar 12 meses. Acorte las fechas e intente de nuevo.',
            'tipo' => 'error',
        ]);
    }
}
