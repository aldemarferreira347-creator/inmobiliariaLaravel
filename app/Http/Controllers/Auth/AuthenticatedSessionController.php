<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión (HU-05).
 *
 * Modelo principal: {@see \App\Models\User}. La validación de credenciales
 * vive en {@see LoginRequest}, no aquí.
 */
class AuthenticatedSessionController extends Controller
{
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

        return redirect()->intended(route($request->user()->rol->rutaInicio()));
    }

    /** Cierra la sesión actual e invalida el token de la petición. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }
}
