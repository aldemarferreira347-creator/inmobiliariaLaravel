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

/**
 * Envío de notificaciones del sistema desde el panel (HU-22.1).
 *
 * El envío en sí (incluyendo el correo opcional) se delega en
 * {@see NotificacionService}; el destino puede ser un usuario, un rol
 * completo ({@see \App\Enumerados\RolUsuario}) o todos los usuarios.
 */
class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /** Formulario para redactar una notificación, con el listado de usuarios activos. */
    public function create(): View
    {
        return view('admin.notificaciones.create', [
            'usuarios' => User::activos()->orderBy('nombre')->get(),
        ]);
    }

    /** Envía la notificación al destino elegido (un usuario, un rol o todos). */
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

    /** Envía la notificación a un único usuario. Devuelve el número de destinatarios (1). */
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

    /** Envía la notificación a todos los usuarios de un rol. Devuelve el número de destinatarios. */
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
