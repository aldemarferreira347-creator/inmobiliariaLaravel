<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImagenInmueble;
use App\Servicios\ImagenInmuebleService;
use Illuminate\Http\RedirectResponse;

/**
 * Portada y borrado de imágenes de la galería de un inmueble (HU-08).
 *
 * Modelo principal: {@see ImagenInmueble}, siempre ligada a un
 * {@see \App\Models\Inmueble}. Las operaciones de archivo se delegan en
 * {@see ImagenInmuebleService}.
 */
class ImagenInmuebleController extends Controller
{
    public function __construct(private readonly ImagenInmuebleService $imagenes) {}

    /** Marca una imagen como la portada del inmueble. */
    public function principal(ImagenInmueble $imagen): RedirectResponse
    {
        $this->authorize('update', $imagen->inmueble);

        $this->imagenes->marcarPrincipal($imagen);

        return $this->volverAlInmueble($imagen, 'Portada actualizada correctamente.');
    }

    /** Elimina una imagen de la galería del inmueble. */
    public function destroy(ImagenInmueble $imagen): RedirectResponse
    {
        $this->authorize('update', $imagen->inmueble);

        $inmuebleId = $imagen->inmueble_id;
        $this->imagenes->eliminar($imagen);

        return $this->volverYReabrirModal($inmuebleId, 'Imagen eliminada correctamente.');
    }

    /** Atajo hacia volverYReabrirModal() a partir de la imagen afectada. */
    private function volverAlInmueble(ImagenInmueble $imagen, string $mensaje): RedirectResponse
    {
        return $this->volverYReabrirModal($imagen->inmueble_id, $mensaje);
    }

    /** Redirige al listado de inmuebles dejando en sesión cuál modal de edición reabrir. */
    // El editar de un inmueble ahora es un modal en el listado, no una página
    // propia: en vez de una URL dedicada a la que redirigir, se deja en la
    // sesión qué inmueble reabrir para que su modal vuelva a aparecer.
    private function volverYReabrirModal(int $inmuebleId, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('admin.inmuebles.index')
            ->with([
                'mensaje' => $mensaje,
                'tipo' => 'success',
                'reabrir_modal_inmueble' => $inmuebleId,
            ]);
    }
}
