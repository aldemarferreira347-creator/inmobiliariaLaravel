<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Solicitud del enlace de recuperación de contraseña (HU-24).
 *
 * El envío del correo con el enlace lo resuelve el broker `Password` de
 * Laravel sobre el modelo {@see User}.
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

        // Se ignora el resultado: responder distinto según exista o no el correo
        // permitiría averiguar quién tiene cuenta (enumeración de usuarios).
        Password::sendResetLink($request->only('email'));

        return back()->with([
            'mensaje' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.',
            'tipo' => 'success',
        ]);
    }
}
