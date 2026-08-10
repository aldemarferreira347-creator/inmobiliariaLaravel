<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImagenInmueble;
use App\Servicios\ImagenInmuebleService;
use Illuminate\Http\RedirectResponse;

// HU-08: portada y borrado de imágenes de la galería de un inmueble
class ImagenInmuebleController extends Controller
{
    public function __construct(private readonly ImagenInmuebleService $imagenes) {}

    public function principal(ImagenInmueble $imagen): RedirectResponse
    {
        $this->authorize('update', $imagen->inmueble);

        $this->imagenes->marcarPrincipal($imagen);

        return $this->volverAlInmueble($imagen, 'Portada actualizada correctamente.');
    }

    public function destroy(ImagenInmueble $imagen): RedirectResponse
    {
        $this->authorize('update', $imagen->inmueble);

        $inmuebleId = $imagen->inmueble_id;
        $this->imagenes->eliminar($imagen);

        return $this->volverYReabrirModal($inmuebleId, 'Imagen eliminada correctamente.');
    }

    private function volverAlInmueble(ImagenInmueble $imagen, string $mensaje): RedirectResponse
    {
        return $this->volverYReabrirModal($imagen->inmueble_id, $mensaje);
    }

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
