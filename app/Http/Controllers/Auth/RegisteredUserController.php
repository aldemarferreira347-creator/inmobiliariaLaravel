<?php

namespace App\Http\Controllers\Auth;

use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroRequest;
use App\Models\User;
use App\Servicios\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// HU-03: registro público de clientes
class RegisteredUserController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function create(): View
    {
        return view('auth.register');
    }

    // El autorregistro solo crea cuentas de cliente: asesores y administradores los da de alta el panel
    public function store(RegistroRequest $request): RedirectResponse
    {
        $usuario = User::create([
            ...$request->validated(),
            'rol' => RolUsuario::Cliente,
        ]);

        // HU-03.4: correo de bienvenida. Su envío no debe impedir el registro.
        $this->notificaciones->paraUsuario(
            $usuario,
            'Bienvenido a Inmobiliaria García',
            'Tu cuenta quedó creada. Ya puedes guardar inmuebles favoritos, reservar y hablar con un asesor.',
            TipoNotificacion::Exito,
            conCorreo: true,
        );

        Auth::login($usuario);

        return redirect()
            ->route('inicio')
            ->with(['mensaje' => '¡Registro exitoso! Bienvenido a Inmobiliaria García.', 'tipo' => 'success']);
    }
}
