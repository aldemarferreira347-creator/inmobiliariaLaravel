<?php

namespace App\Servicios;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos que no deben servirse desde la raíz pública: contratos firmados y
 * escrituras. Se guardan en el disco privado y solo salen a través de un
 * controlador que comprueba antes quién los pide.
 */
class ArchivoPrivadoService
{
    private const DISCO = 'local';

    public function guardar(UploadedFile $archivo, string $carpeta, string $nombre): string
    {
        return $archivo->storeAs($carpeta, $nombre.'.'.$archivo->extension(), self::DISCO);
    }

    public function eliminar(?string $ruta): void
    {
        if (blank($ruta)) {
            return;
        }

        Storage::disk(self::DISCO)->delete($ruta);
    }

    public function existe(string $ruta): bool
    {
        return Storage::disk(self::DISCO)->exists($ruta);
    }

    public function descargar(string $ruta, string $nombreVisible): StreamedResponse
    {
        return Storage::disk(self::DISCO)->download($ruta, $nombreVisible);
    }
}
