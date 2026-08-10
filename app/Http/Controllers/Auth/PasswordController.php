<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// HU-25.2: cambio de contraseña desde la sesión iniciada
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('perfil.cambiar-password');
    }

    public function update(CambiarPasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['contrasena' => $request->validated('contrasena')]);

        return redirect()
            ->route('perfil.edit')
            ->with(['mensaje' => 'Contraseña actualizada correctamente.', 'tipo' => 'success']);
    }
}
