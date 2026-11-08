<?php

namespace App\Servicios\Reportes;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

        $this->escribirFilas($hoja, $filas->all());
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

    /**
     * Los textos se escriben como texto, nunca como fórmula: un título de
     * inmueble o una notaría que empiece por «=» no debe ejecutarse al abrir
     * el archivo (inyección de fórmulas en hojas de cálculo).
     *
     * @param  array<int, array<int, string|int|float|null>>  $filas
     */
    private function escribirFilas($hoja, array $filas): void
    {
        foreach ($filas as $indiceFila => $fila) {
            foreach (array_values($fila) as $indiceColumna => $valor) {
                $celda = $this->letraColumna($indiceColumna + 1).(5 + $indiceFila);

                if (is_int($valor) || is_float($valor)) {
                    $hoja->setCellValue($celda, $valor);
                } else {
                    $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
                }
            }
        }
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

    // Punto de extensión para las pruebas del fallo al guardar (HU-06.3)
    protected function escritor(Spreadsheet $libro): Xlsx
    {
        return new Xlsx($libro);
    }

    private function responder(Spreadsheet $libro, Reporte $reporte): StreamedResponse
    {
        $nombre = "reporte_{$reporte->tipo()->value}_".now()->format('Y-m-d_H-i').'.xlsx';

        // El archivo se genera aquí, dentro del try del controlador: si save() falla
        // se muestra la pantalla de error con «Intentar nuevamente» (HU-06.3) en vez
        // de cortar una descarga ya empezada. php://temp pasa a disco por encima de 2 MB.
        $archivo = fopen('php://temp', 'w+b');

        try {
            $this->escritor($libro)->save($archivo);
        } finally {
            // Libera las celdas antes de enviar el archivo
            $libro->disconnectWorksheets();
        }

        rewind($archivo);

        return response()->streamDownload(function () use ($archivo) {
            fpassthru($archivo);
            fclose($archivo);
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
