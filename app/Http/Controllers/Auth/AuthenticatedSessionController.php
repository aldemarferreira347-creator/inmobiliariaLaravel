<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión (HU-05).
 *
 * Modelo principal: {@see User}. La validación de credenciales
 * vive en {@see LoginRequest}, no aquí.
 */
class AuthenticatedSessionController extends Controller
{
    /** Cookie que recuerda que hubo una sesión iniciada (ver bootstrap/app.php). */
    public const COOKIE_SESION = 'sesion_iniciada';

    /** Muestra el formulario de inicio de sesión. */
    public function create(): View
    {
        return view('auth.login');
    }

    /** Autentica y lleva al usuario a la pantalla inicial de su rol (HU-05.1). */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // HU-05.4: marca que distingue «sesión expirada» de «nunca inició sesión»
        Cookie::queue(Cookie::make(self::COOKIE_SESION, '1', 60 * 24 * 30, httpOnly: true));

        return redirect()->intended(route($request->user()->rol->rutaInicio()));
    }

    /** Cierra la sesión actual e invalida el token de la petición. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(self::COOKIE_SESION));

        return redirect()->route('inicio');
    }
}
