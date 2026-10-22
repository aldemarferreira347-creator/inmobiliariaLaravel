<?php

namespace App\Soporte;

use App\Models\Notificacion;
use App\Models\User;

/**
 * Pantalla donde cada usuario consulta la entidad a la que apunta una notificación.
 *
 * El mismo aviso (p. ej. «contrato vencido») llega a clientes y a personal, y
 * cada rol ve esa entidad en una pantalla distinta: enlazar siempre a la del
 * panel mandaba a los clientes a un 403. Devuelve null cuando el usuario no
 * tiene dónde verla.
 */
final class EnlaceNotificacion
{
    public static function para(Notificacion $notificacion, User $usuario): ?string
    {
        $id = $notificacion->referencia_id;

        if ($id === null) {
            return null;
        }

        return match ($notificacion->referencia_tipo) {
            'reserva' => route($usuario->esAdministrador() ? 'admin.reservas.show' : 'reservas.show', $id),
            'contrato' => match (true) {
                $usuario->esAdministrador() => route('admin.contratos.show', $id),
                $usuario->esCliente() => route('perfil.edit'),
                default => null,
            },
            'cita' => match (true) {
                $usuario->esAdministrador() => route('admin.citas.show', $id),
                $usuario->esAsesor() => route('asesor.citas.index'),
                default => route('citas.index'),
            },
            'inmueble' => route('inmuebles.show', $id),
            'conversacion' => route('mensajes.show', $id),
            default => null,
        };
    }
}
