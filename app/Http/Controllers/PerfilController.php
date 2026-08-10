<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoReserva;
use App\Enumerados\ModalidadInmueble;
use App\Http\Requests\FotoPerfilRequest;
use App\Http\Requests\PerfilUpdateRequest;
use App\Servicios\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-25: perfil del usuario autenticado
class PerfilController extends Controller
{
    public function __construct(private readonly AvatarService $avatares) {}

    public function edit(Request $request): View
    {
        return view('perfil.edit', [
            'usuario' => $request->user(),
            'totalFavoritos' => $request->user()->favoritos()->count(),
        ]);
    }

    public function update(PerfilUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return $this->volverAlPerfil('Perfil actualizado correctamente.');
    }

    public function actualizarFoto(FotoPerfilRequest $request): RedirectResponse
    {
        $this->avatares->reemplazar($request->user(), $request->file('foto'));

        return $this->volverAlPerfil('Foto de perfil actualizada correctamente.');
    }

    public function eliminarFoto(Request $request): RedirectResponse
    {
        $this->avatares->eliminar($request->user());

        return $this->volverAlPerfil('Foto de perfil eliminada.');
    }

    /**
     * HU-19.1: arriendos del cliente. Una reserva confirmada sin contrato aún
     * se muestra como «Contrato pendiente» mientras corre el plazo de RN-18.
     */
    public function misArriendos(Request $request): View
    {
        return view('perfil.mis-arriendos', [
            'usuario' => $request->user(),
            'reservas' => $request->user()
                ->reservas()
                ->where('estado', EstadoReserva::Confirmada)
                ->whereHas('inmueble', fn ($q) => $q->whereIn('modalidad', [ModalidadInmueble::Arriendo, ModalidadInmueble::Ambos]))
                ->with('inmueble', 'contrato')
                ->recientes()
                ->get(),
        ]);
    }

    // HU-19.2: compras del cliente
    public function misCompras(Request $request): View
    {
        return view('perfil.mis-compras', [
            'usuario' => $request->user(),
            'ventas' => $request->user()
                ->ventas()
                ->with('inmueble', 'asesor')
                ->latest('fecha_venta')
                ->get(),
        ]);
    }

    private function volverAlPerfil(string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('perfil.edit')
            ->with(['mensaje' => $mensaje, 'tipo' => 'success']);
    }
}
