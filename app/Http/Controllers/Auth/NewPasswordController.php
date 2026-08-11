<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Reglas\PasswordSegura;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Aplicación de la nueva contraseña desde el enlace recibido por correo (HU-24).
 *
 * Modelo principal: {@see User}. El token y su expiración los gestiona el
 * broker `Password` de Laravel, no este controlador.
 */
class NewPasswordController extends Controller
{
    /** Muestra el formulario para definir la nueva contraseña, con el token recibido por correo. */
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    /** Valida el token de recuperación y guarda la nueva contraseña. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'contrasena' => ['required', 'confirmed', PasswordSegura::reglas()],
        ]);

        $estado = Password::reset(
            [
                'email' => $request->input('email'),
                'password' => $request->input('contrasena'),
                'password_confirmation' => $request->input('contrasena_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $usuario) use ($request) {
                // El esquema nombra la columna `contrasena`; se renueva el token
                // de sesión persistente para invalidar los «recuérdame» previos.
                $usuario->forceFill([
                    'contrasena' => Hash::make($request->input('contrasena')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($usuario));
            }
        );

        return $estado === Password::PASSWORD_RESET
            ? redirect()->route('login')->with(['mensaje' => __($estado), 'tipo' => 'success'])
            : back()->withInput($request->only('email'))->withErrors(['email' => __($estado)]);
    }
}
