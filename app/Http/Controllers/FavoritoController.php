<?php

namespace App\Http\Controllers;

use App\Models\Inmueble;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-18: inmuebles marcados como favoritos por el usuario autenticado
class FavoritoController extends Controller
{
    public function index(Request $request): View
    {
        return view('perfil.favoritos', [
            'usuario' => $request->user(),
            'inmuebles' => $request->user()->favoritos()->recientes()->get(),
        ]);
    }

    // Añade o quita el inmueble de la lista según su estado actual
    public function toggle(Request $request, Inmueble $inmueble): RedirectResponse
    {
        $cambios = $request->user()->favoritos()->toggle($inmueble);
        $agregado = filled($cambios['attached']);

        return back()->with([
            'mensaje' => $agregado ? 'Inmueble añadido a favoritos.' : 'Inmueble retirado de favoritos.',
            'tipo' => 'success',
        ]);
    }
}
