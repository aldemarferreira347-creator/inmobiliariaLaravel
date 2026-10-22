<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Cambio de contraseña desde la sesión iniciada (HU-25.2).
 *
 * Modelo principal: {@see User}, la contraseña actual del
 * usuario autenticado.
 */
class PasswordController extends Controller
{
    /** Actualiza la contraseña del usuario autenticado. */
    public function update(CambiarPasswordRequest $request): RedirectResponse
    {
        // Renovar el token de «recuérdame» y la sesión: quien tuviera la cuenta
        // abierta en otro navegador (o la hubiera robado) deja de entrar.
        $request->user()->forceFill([
            'contrasena' => $request->validated('contrasena'),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return redirect()
            ->route('perfil.edit')
            ->with(['mensaje' => 'Contraseña actualizada correctamente.', 'tipo' => 'success']);
    }
}
