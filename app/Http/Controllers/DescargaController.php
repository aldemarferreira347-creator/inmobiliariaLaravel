<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Venta;
use App\Servicios\ArchivoPrivadoService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega de documentos guardados fuera de la raíz pública.
 * Cada descarga comprueba antes que quien la pide tenga derecho a ella.
 */
class DescargaController extends Controller
{
    public function __construct(private readonly ArchivoPrivadoService $archivos) {}

    /** Descarga el PDF de un {@see Contrato} si el usuario autenticado tiene permiso sobre él. */
    public function contrato(Contrato $contrato): StreamedResponse
    {
        $this->authorize('descargar', $contrato);

        abort_unless($this->archivos->existe($contrato->archivo_ruta), 404);

        return $this->archivos->descargar($contrato->archivo_ruta, "{$contrato->numero_contrato}.pdf");
    }

    /** Descarga la escritura PDF de una {@see Venta} si el usuario autenticado tiene permiso sobre ella. */
    public function escritura(Venta $venta): StreamedResponse
    {
        $this->authorize('descargarEscritura', $venta);

        abort_unless($this->archivos->existe($venta->escritura_ruta), 404);

        return $this->archivos->descargar($venta->escritura_ruta, "escritura-venta-{$venta->id}.pdf");
    }
}
