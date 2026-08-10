<?php

namespace App\Servicios;

use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Mail\Aviso;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emisión de notificaciones in-app y, cuando corresponde, del correo que las
 * acompaña (HU-15 / HU-22 / HU-23).
 *
 * Un fallo al notificar nunca revierte la operación que lo originó: la reserva
 * ya se confirmó aunque el servidor de correo esté caído.
 */
class NotificacionService
{
    public function paraUsuario(
        User $usuario,
        string $titulo,
        string $mensaje,
        TipoNotificacion $tipo = TipoNotificacion::Info,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        bool $conCorreo = false,
    ): void {
        $this->insertar([$usuario->id], $titulo, $mensaje, $tipo, $referenciaTipo, $referenciaId);

        if ($conCorreo) {
            $this->enviarCorreo([$usuario], $titulo, $mensaje);
        }
    }

    // Avisa a todos los usuarios activos de un rol
    public function paraRol(
        RolUsuario $rol,
        string $titulo,
        string $mensaje,
        TipoNotificacion $tipo = TipoNotificacion::Info,
        bool $conCorreo = false,
    ): int {
        $usuarios = User::delRol($rol)->activos()->get();

        $this->insertar($usuarios->modelKeys(), $titulo, $mensaje, $tipo);

        if ($conCorreo) {
            $this->enviarCorreo($usuarios, $titulo, $mensaje);
        }

        return $usuarios->count();
    }

    // Avisa al equipo interno: administradores y asesores activos
    public function paraStaff(
        string $titulo,
        string $mensaje,
        TipoNotificacion $tipo = TipoNotificacion::Info,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): void {
        $ids = User::query()
            ->activos()
            ->whereIn('rol', [RolUsuario::Administrador, RolUsuario::Asesor])
            ->pluck('id');

        $this->insertar($ids->all(), $titulo, $mensaje, $tipo, $referenciaTipo, $referenciaId);
    }

    public function paraTodos(
        string $titulo,
        string $mensaje,
        TipoNotificacion $tipo = TipoNotificacion::Sistema,
        bool $conCorreo = false,
    ): int {
        $usuarios = User::activos()->get();

        $this->insertar($usuarios->modelKeys(), $titulo, $mensaje, $tipo);

        if ($conCorreo) {
            $this->enviarCorreo($usuarios, $titulo, $mensaje);
        }

        return $usuarios->count();
    }

    /**
     * Inserta las filas en una sola consulta.
     * El prototipo hacía un INSERT por usuario dentro de un bucle.
     *
     * @param  array<int, int>  $usuarioIds
     */
    private function insertar(
        array $usuarioIds,
        string $titulo,
        string $mensaje,
        TipoNotificacion $tipo,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): void {
        if ($usuarioIds === []) {
            return;
        }

        $ahora = now();

        $filas = Collection::make($usuarioIds)->map(fn (int $id) => [
            'usuario_id' => $id,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo->value,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'creado_en' => $ahora,
        ]);

        Notificacion::insert($filas->all());
    }

    /**
     * @param  iterable<int, User>  $usuarios
     */
    private function enviarCorreo(iterable $usuarios, string $titulo, string $mensaje): void
    {
        $aviso = new Aviso($titulo, [$mensaje]);

        foreach ($usuarios as $usuario) {
            try {
                Mail::to($usuario->email)->queue($aviso);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
