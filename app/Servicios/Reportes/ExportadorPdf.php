<?php

namespace App\Servicios\Reportes;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación de un reporte a PDF.
 *
 * La numeración de páginas se pinta sobre el lienzo nativo de Dompdf en vez de
 * habilitar la ejecución de PHP dentro del HTML, que sería un riesgo evitable.
 */
class ExportadorPdf
{
    public function exportar(Reporte $reporte): StreamedResponse
    {
        $opciones = new Options;
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'Helvetica');
        $opciones->set('dpi', 96);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml(view('admin.reportes.pdf', ['reporte' => $reporte])->render());
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $this->numerarPaginas($dompdf);

        $nombre = "reporte_{$reporte->tipo()->value}_".now()->format('Y-m-d_H-i').'.pdf';

        return response()->streamDownload(
            fn () => print ($dompdf->output()),
            $nombre,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function numerarPaginas(Dompdf $dompdf): void
    {
        $lienzo = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica');

        $lienzo->page_text(
            $lienzo->get_width() / 2 - 40,
            $lienzo->get_height() - 24,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fuente,
            8,
            [0.39, 0.45, 0.55],
        );
    }
}
