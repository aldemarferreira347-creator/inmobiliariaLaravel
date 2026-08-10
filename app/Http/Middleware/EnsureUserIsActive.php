<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta la sesión de un usuario desactivado en su siguiente petición (HU-16.2).
 *
 * Sin esto, desactivar una cuenta solo impediría iniciar sesión de nuevo: quien
 * ya tuviera la sesión abierta seguiría navegando hasta que expirara. Se aplica
 * a todo el grupo 'web' (ver bootstrap/app.php) para cubrir tanto rutas públicas
 * como privadas, igual que el callAction() global que reemplaza.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->estaActivo()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with([
                'mensaje' => 'Tu cuenta está desactivada. Contacta al administrador.',
                'tipo' => 'error',
            ]);
        }

        return $next($request);
    }
}
