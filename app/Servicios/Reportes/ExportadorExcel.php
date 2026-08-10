<?php

namespace App\Servicios\Reportes;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación de un reporte a Excel con la identidad de la marca.
 *
 * Los importes se escriben como números con formato de moneda, no como texto,
 * para que se puedan sumar y ordenar dentro de la hoja.
 */
class ExportadorExcel
{
    private const NAVY = 'FF0F1E4A';

    private const NAVY_CLARO = 'FF1E3177';

    private const DORADO = 'FFF5A623';

    private const BLANCO = 'FFFFFFFF';

    private const GRIS = 'FFF8FAFF';

    private const FORMATO_MONEDA = '"$"#,##0';

    public function exportar(Reporte $reporte): StreamedResponse
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr($reporte->titulo(), 0, 31));

        $columnas = $reporte->columnas();
        $ultima = $this->letraColumna(count($columnas));

        $this->escribirCabecera($hoja, $reporte, $ultima);
        $this->escribirTabla($hoja, $reporte, $ultima);

        $libro->getProperties()
            ->setCreator('Inmobiliaria García')
            ->setTitle("Reporte de {$reporte->titulo()}");

        return $this->responder($libro, $reporte);
    }

    private function escribirCabecera($hoja, Reporte $reporte, string $ultima): void
    {
        $hoja->setCellValue('A1', 'INMOBILIARIA GARCÍA — '.mb_strtoupper($reporte->titulo()));
        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => self::BLANCO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $hoja->setCellValue('A2', 'Periodo: '.$reporte->filtro()->descripcion().'  ·  Generado: '.now()->format('d/m/Y H:i'));
        $hoja->mergeCells("A2:{$ultima}2");
        $hoja->getStyle("A2:{$ultima}2")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FFFFD580']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NAVY_CLARO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Franja dorada de separación
        $hoja->mergeCells("A3:{$ultima}3");
        $hoja->getRowDimension(3)->setRowHeight(4);
        $hoja->getStyle("A3:{$ultima}3")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::DORADO);
    }

    private function escribirTabla($hoja, Reporte $reporte, string $ultima): void
    {
        $columnas = $reporte->columnas();
        $filas = $reporte->filas();
        $numericas = $reporte->columnasNumericas();

        $hoja->fromArray($columnas, null, 'A4');
        $hoja->getStyle("A4:{$ultima}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::BLANCO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NAVY_CLARO]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::DORADO]]],
        ]);

        if ($filas->isEmpty()) {
            $hoja->setCellValue('A5', 'No hay registros disponibles para los filtros seleccionados.');
            $hoja->mergeCells("A5:{$ultima}5");
            $this->ajustarAnchos($hoja, count($columnas));

            return;
        }

        $hoja->fromArray($filas->all(), null, 'A5');
        $ultimaFila = 4 + $filas->count();

        // Filas alternas para facilitar la lectura
        foreach (range(5, $ultimaFila) as $fila) {
            if ($fila % 2 === 1) {
                $hoja->getStyle("A{$fila}:{$ultima}{$fila}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GRIS);
            }
        }

        foreach ($numericas as $indice) {
            $letra = $this->letraColumna($indice + 1);
            $hoja->getStyle("{$letra}5:{$letra}{$ultimaFila}")
                ->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA);
        }

        $hoja->setAutoFilter("A4:{$ultima}{$ultimaFila}");
        $hoja->freezePane('A5');
        $this->ajustarAnchos($hoja, count($columnas));
    }

    private function ajustarAnchos($hoja, int $total): void
    {
        foreach (range(1, $total) as $indice) {
            $hoja->getColumnDimension($this->letraColumna($indice))->setAutoSize(true);
        }
    }

    private function letraColumna(int $indice): string
    {
        return chr(64 + $indice);
    }

    private function responder(Spreadsheet $libro, Reporte $reporte): StreamedResponse
    {
        $nombre = "reporte_{$reporte->tipo()->value}_".now()->format('Y-m-d_H-i').'.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
