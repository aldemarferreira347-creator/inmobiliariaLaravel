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
    /** Filas máximas por exportación: medido, ~0,5 MB y ~17 ms por fila en PDF; Excel 2 000 filas = 87 MB */
    public const MAX_FILAS_PDF = 200;

    public const MAX_FILAS_EXCEL = 5000;

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
    public function excel(Request $request, TipoReporte $tipo, ExportadorExcel $exportador): View|StreamedResponse|RedirectResponse
    {
        return $this->exportar($request, $tipo, self::MAX_FILAS_EXCEL, 'Excel', $exportador);
    }

    /** Exporta el reporte filtrado a PDF. */
    public function pdf(Request $request, TipoReporte $tipo, ExportadorPdf $exportador): View|StreamedResponse|RedirectResponse
    {
        return $this->exportar($request, $tipo, self::MAX_FILAS_PDF, 'PDF', $exportador);
    }

    /**
     * Exporta con tres topes: rango de fechas, número de filas y fallo al construir.
     * Los topes salen de la medición con 2 000 filas (docs/despliegue/infinityfree.md).
     */
    private function exportar(Request $request, TipoReporte $tipo, int $maxFilas, string $formato, ExportadorExcel|ExportadorPdf $exportador): View|StreamedResponse|RedirectResponse
    {
        $filtro = FiltroReporte::desdePeticion($request);

        if ($rechazo = $this->rangoExcedido($tipo, $filtro)) {
            return $rechazo;
        }

        try {
            $reporte = $this->reportes->crear($tipo, $filtro);
            $total = $reporte->total();

            if ($total > $maxFilas) {
                $alternativa = $formato === 'PDF' ? ' o exporte a Excel' : '';
                $maximo = number_format($maxFilas, 0, ',', '.');

                return back()->with([
                    'mensaje' => "El reporte tiene {$total} filas y el {$formato} admite hasta {$maximo}. Acorte el rango{$alternativa}.",
                    'tipo' => 'error',
                ]);
            }

            return $exportador->exportar($reporte);
        } catch (Throwable $e) {
            return $this->errorDeGeneracion($e);
        }
    }

    /**
     * Limita el rango exportable (31 días el diario, 12 meses el resto) como
     * segunda guarda además del tope de filas.
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
