<?php

namespace Database\Seeders;

use App\Enumerados\RolUsuario;
use App\Models\Inmueble;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

// Catálogo de demostración; las operaciones sobre él las siembra OperacionSeeder
class InmuebleSeeder extends Seeder
{
    public function run(): void
    {
        $inmuebles = Inmueble::factory()->count(12)->create();

        $inmuebles->each($this->sembrarGaleria(...));

        $cliente = User::delRol(RolUsuario::Cliente)->firstOrFail();

        $cliente->favoritos()->syncWithoutDetaching($inmuebles->take(3)->pluck('id'));
    }

    /**
     * Crea 2 imágenes de galería por inmueble, generadas localmente con GD
     * (mismo tono de marca que el placeholder de Inmueble::urlDeImagen, pero
     * servidas desde disco en vez de un servicio externo). Sin esto, el
     * catálogo de demostración no ejercitaba nunca la galería real (HU-08) y
     * dependía de una CDN externa (placehold.co) para no verse vacío.
     */
    private function sembrarGaleria(Inmueble $inmueble): void
    {
        foreach ([1, 2] as $orden) {
            $ruta = $this->generarImagen($inmueble, $orden);
            $esPrimera = $orden === 1;

            $imagen = $inmueble->imagenes()->create([
                'url' => $ruta,
                'es_principal' => $esPrimera,
                'orden' => $orden,
            ]);

            if ($esPrimera) {
                $inmueble->update(['imagen' => $imagen->url]);
            }
        }
    }

    private function generarImagen(Inmueble $inmueble, int $orden): string
    {
        $ancho = 800;
        $alto = 533;

        $lienzo = imagecreatetruecolor($ancho, $alto);

        // Degradado vertical con el mismo tono de marca (#1e3c72 → #2a5298)
        // que usa Inmueble::urlDeImagen() como respaldo sin imagen.
        for ($y = 0; $y < $alto; $y++) {
            $mezcla = $y / $alto;
            $color = imagecolorallocate(
                $lienzo,
                (int) round(30 + (42 - 30) * $mezcla),
                (int) round(60 + (82 - 60) * $mezcla),
                (int) round(114 + (152 - 114) * $mezcla),
            );
            imageline($lienzo, 0, $y, $ancho, $y, $color);
        }

        // Silueta simple de una casa, para que no sea un rectángulo vacío
        $trazo = imagecolorallocatealpha($lienzo, 255, 255, 255, 105);
        $centroX = (int) ($ancho / 2);
        imagefilledpolygon($lienzo, [
            $centroX - 90, 260,
            $centroX, 180,
            $centroX + 90, 260,
        ], $trazo);
        imagefilledrectangle($lienzo, $centroX - 70, 260, $centroX + 70, 340, $trazo);

        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $titulo = mb_strtoupper($inmueble->titulo);
        $anchoTitulo = imagefontwidth(5) * strlen($titulo);
        imagestring($lienzo, 5, (int) ($ancho / 2 - $anchoTitulo / 2), 380, $titulo, $blanco);

        $anchoCodigo = imagefontwidth(3) * strlen($inmueble->codigo);
        imagestring($lienzo, 3, (int) ($ancho / 2 - $anchoCodigo / 2), 405, $inmueble->codigo, $blanco);

        $nombreArchivo = "inmuebles/seed-{$inmueble->id}-{$orden}.jpg";
        Storage::disk('public')->makeDirectory('inmuebles');
        imagejpeg($lienzo, Storage::disk('public')->path($nombreArchivo), 85);
        imagedestroy($lienzo);

        return 'storage/'.$nombreArchivo;
    }
}
