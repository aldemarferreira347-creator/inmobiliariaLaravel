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

        $estado = Password::sendResetLink($request->only('email'));

        // HU-24.2: se informa si el correo no tiene cuenta (riesgo aceptado de
        // enumeración, documentado en docs/despliegue/infinityfree.md).
        if ($estado !== Password::RESET_LINK_SENT) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($estado)]);
        }

        return back()->with(['mensaje' => __($estado), 'tipo' => 'success']);
    }
}
