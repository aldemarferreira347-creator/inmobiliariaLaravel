<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoReserva;
use App\Http\Requests\FotoPerfilRequest;
use App\Http\Requests\PerfilUpdateRequest;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Servicios\AvatarService;
use App\Servicios\StripeCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil del usuario autenticado (HU-25).
 *
 * Modelo principal: {@see User}. El manejo del archivo de foto
 * se delega en {@see AvatarService}; `misArriendos()` y `misCompras()`
 * cruzan además con {@see Reserva} y {@see Venta}.
 */
class PerfilController extends Controller
{
    public function __construct(
        private readonly AvatarService $avatares,
        private readonly StripeCardService $tarjetas,
    ) {}

    /**
     * Muestra el perfil del usuario autenticado.
     *
     * Todas las secciones de la cuenta (contraseña, arriendos, compras,
     * tarjetas) se resuelven en esta única vista porque se abren como
     * modales sobre la misma página, nunca como una vista aparte.
     */
    public function edit(Request $request): View
    {
        $usuario = $request->user();

        return view('perfil.edit', [
            'usuario' => $usuario,
            'totalFavoritos' => $usuario->favoritos()->count(),
            'reservas' => $usuario->reservas()
                ->where('estado', EstadoReserva::Confirmada)
                ->deArriendo()
                ->with('inmueble', 'contrato', 'pagos')
                ->recientes()
                ->get(),
            'ventas' => $usuario->ventas()
                ->with('inmueble', 'asesor')
                ->latest('fecha_venta')
                ->get(),
            'tarjetasGuardadas' => $this->tarjetas->listar($usuario),
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    /** Actualiza los datos básicos del perfil del usuario autenticado. */
    public function update(PerfilUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return $this->volverAlPerfil('Perfil actualizado exitosamente');
    }

    /** Reemplaza la foto de perfil del usuario autenticado. */
    public function actualizarFoto(FotoPerfilRequest $request): RedirectResponse
    {
        $this->avatares->reemplazar($request->user(), $request->file('foto'));

        return $this->volverAlPerfil('Foto de perfil actualizada correctamente.');
    }

    /** Elimina la foto de perfil del usuario autenticado. */
    public function eliminarFoto(Request $request): RedirectResponse
    {
        $this->avatares->eliminar($request->user());

        return $this->volverAlPerfil('Foto de perfil eliminada.');
    }

    /** Redirige a la edición del perfil con un mensaje flash de éxito. */
    private function volverAlPerfil(string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('perfil.edit')
            ->with(['mensaje' => $mensaje, 'tipo' => 'success']);
    }
}
