<?php

namespace App\Servicios;

use App\Models\ImagenInmueble;
use App\Models\Inmueble;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Galería de imágenes de un inmueble (HU-08).
 *
 * Concentra el acceso al sistema de archivos y la coherencia de la portada:
 * `inmueble.imagen` siempre refleja la imagen marcada como principal, para que
 * los listados no necesiten unir con `imageninmueble`.
 */
class ImagenInmuebleService
{
    private const DIRECTORIO = 'inmuebles';

    /** Ancho máximo antes de redimensionar; por encima se reescala manteniendo la proporción */
    private const ANCHO_MAXIMO = 1600;

    private const CALIDAD_JPEG = 82;

    /**
     * Añade imágenes a la galería. La primera pasa a ser portada si el inmueble
     * aún no tenía ninguna.
     *
     * @param  array<int, UploadedFile>  $archivos
     */
    public function agregar(Inmueble $inmueble, array $archivos): void
    {
        $orden = (int) $inmueble->imagenes()->max('orden');

        foreach ($archivos as $archivo) {
            $ruta = $this->guardar($archivo);
            $esPrimera = ! $inmueble->imagenes()->exists();

            $imagen = $inmueble->imagenes()->create([
                'url' => $ruta,
                'es_principal' => $esPrimera,
                'orden' => ++$orden,
            ]);

            if ($esPrimera) {
                $this->sincronizarPortada($inmueble, $imagen);
            }
        }
    }

    // Marca una imagen como portada y desmarca la anterior
    public function marcarPrincipal(ImagenInmueble $imagen): void
    {
        DB::transaction(function () use ($imagen) {
            $inmueble = $imagen->inmueble;

            $inmueble->imagenes()->update(['es_principal' => false]);
            $imagen->update(['es_principal' => true]);

            $this->sincronizarPortada($inmueble, $imagen);
        });
    }

    /**
     * Elimina la imagen y su archivo físico. Si era la portada, promueve la
     * siguiente disponible; la eliminación en cascada de la base de datos no
     * alcanza al sistema de archivos, por eso se borra aquí explícitamente.
     */
    public function eliminar(ImagenInmueble $imagen): void
    {
        DB::transaction(function () use ($imagen) {
            $inmueble = $imagen->inmueble;
            $eraPortada = $imagen->es_principal;

            Storage::disk('public')->delete($this->rutaEnDisco($imagen->url));
            $imagen->delete();

            if (! $eraPortada) {
                return;
            }

            $reemplazo = $inmueble->imagenes()->first();
            $reemplazo?->update(['es_principal' => true]);

            $this->sincronizarPortada($inmueble, $reemplazo);
        });
    }

    // Retira del disco todas las imágenes antes de eliminar el inmueble
    public function eliminarTodas(Inmueble $inmueble): void
    {
        $rutas = $inmueble->imagenes()->pluck('url')->map($this->rutaEnDisco(...))->all();

        Storage::disk('public')->delete($rutas);
    }

    private function sincronizarPortada(Inmueble $inmueble, ?ImagenInmueble $imagen): void
    {
        $inmueble->update(['imagen' => $imagen?->url]);
    }

    /**
     * Guarda la imagen optimizada y devuelve su ruta pública.
     * Las que superan el ancho máximo se reescalan para no servir archivos
     * innecesariamente pesados en el catálogo.
     */
    private function guardar(UploadedFile $archivo): string
    {
        $ruta = $archivo->store(self::DIRECTORIO, 'public');
        $this->redimensionarSiExcede(Storage::disk('public')->path($ruta));

        return 'storage/'.$ruta;
    }

    private function redimensionarSiExcede(string $rutaAbsoluta): void
    {
        [$ancho, $alto, $tipo] = getimagesize($rutaAbsoluta) ?: [0, 0, 0];

        if ($ancho <= self::ANCHO_MAXIMO) {
            return;
        }

        $original = match ($tipo) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($rutaAbsoluta),
            IMAGETYPE_PNG => imagecreatefrompng($rutaAbsoluta),
            IMAGETYPE_WEBP => imagecreatefromwebp($rutaAbsoluta),
            default => null,
        };

        if ($original === null || $original === false) {
            return;
        }

        $nuevoAlto = (int) round($alto * (self::ANCHO_MAXIMO / $ancho));
        $reducida = imagescale($original, self::ANCHO_MAXIMO, $nuevoAlto);

        match ($tipo) {
            IMAGETYPE_JPEG => imagejpeg($reducida, $rutaAbsoluta, self::CALIDAD_JPEG),
            IMAGETYPE_PNG => imagepng($reducida, $rutaAbsoluta),
            IMAGETYPE_WEBP => imagewebp($reducida, $rutaAbsoluta, self::CALIDAD_JPEG),
        };

        imagedestroy($original);
        imagedestroy($reducida);
    }

    // Las URL se guardan con el prefijo `storage/` que usa asset(); el disco no lo lleva
    private function rutaEnDisco(string $url): string
    {
        return str_replace('storage/', '', $url);
    }
}
