<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cambio de contraseña desde la sesión iniciada (HU-25.2).
 *
 * Modelo principal: {@see \App\Models\User}, la contraseña actual del
 * usuario autenticado.
 */
class PasswordController extends Controller
{
    /** Muestra el formulario para cambiar la contraseña. */
    public function edit(): View
    {
        return view('perfil.cambiar-password');
    }

    /** Actualiza la contraseña del usuario autenticado. */
    public function update(CambiarPasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['contrasena' => $request->validated('contrasena')]);

        return redirect()
            ->route('perfil.edit')
            ->with(['mensaje' => 'Contraseña actualizada correctamente.', 'tipo' => 'success']);
    }
}
