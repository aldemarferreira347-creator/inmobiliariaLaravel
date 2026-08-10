<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnviarNotificacionRequest;
use App\Models\User;
use App\Servicios\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// HU-22.1: envío de notificaciones del sistema desde el panel
class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function create(): View
    {
        return view('admin.notificaciones.create', [
            'usuarios' => User::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function store(EnviarNotificacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $conCorreo = $request->boolean('enviar_correo');

        $alcance = match ($datos['destino']) {
            'usuario' => $this->aUnUsuario($datos, $conCorreo),
            'rol' => $this->aUnRol($datos, $conCorreo),
            'todos' => $this->notificaciones->paraTodos(
                $datos['titulo'],
                $datos['mensaje'],
                TipoNotificacion::Sistema,
                $conCorreo,
            ),
        };

        return redirect()
            ->route('admin.notificaciones.create')
            ->with(['mensaje' => "Notificación enviada a {$alcance} usuario(s).", 'tipo' => 'success']);
    }

    private function aUnUsuario(array $datos, bool $conCorreo): int
    {
        $destinatario = User::findOrFail($datos['usuario_id']);

        $this->notificaciones->paraUsuario(
            $destinatario,
            $datos['titulo'],
            $datos['mensaje'],
            TipoNotificacion::Sistema,
            conCorreo: $conCorreo,
        );

        return 1;
    }

    private function aUnRol(array $datos, bool $conCorreo): int
    {
        return $this->notificaciones->paraRol(
            RolUsuario::from($datos['rol']),
            $datos['titulo'],
            $datos['mensaje'],
            TipoNotificacion::Sistema,
            $conCorreo,
        );
    }
}
