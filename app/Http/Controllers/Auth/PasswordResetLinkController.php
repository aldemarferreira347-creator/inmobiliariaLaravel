<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Solicitud del enlace de recuperación de contraseña (HU-24).
 *
 * El envío del correo con el enlace lo resuelve el broker `Password` de
 * Laravel sobre el modelo {@see \App\Models\User}.
 */
class PasswordResetLinkController extends Controller
{
    /** Muestra el formulario para solicitar el enlace de recuperación. */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /** Envía el correo con el enlace de recuperación de contraseña. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $estado = Password::sendResetLink($request->only('email'));

        return $estado === Password::RESET_LINK_SENT
            ? back()->with(['mensaje' => __($estado), 'tipo' => 'success'])
            : back()->withInput($request->only('email'))->withErrors(['email' => __($estado)]);
    }
}
