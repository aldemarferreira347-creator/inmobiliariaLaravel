<?php

namespace Database\Seeders;

use App\Enumerados\RolUsuario;
use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Matriz de permisos por rol (RF-25.4 / CU-20).
 * Es el catálogo informativo que se consulta en /admin/permisos.
 */
class PermisoSeeder extends Seeder
{
    /** @var array<string, array<string, array<int, string>>> rol => módulo => acciones */
    private const MATRIZ = [
        RolUsuario::Administrador->value => [
            'inmuebles' => ['create', 'read', 'update', 'delete'],
            'usuarios' => ['create', 'read', 'update', 'delete'],
            'reservas' => ['read', 'update'],
            'pagos' => ['read', 'update'],
            'contratos' => ['create', 'read', 'update'],
            'ventas' => ['read'],
            'mensajes' => ['read', 'create'],
            'notificaciones' => ['create', 'read'],
            'reportes' => ['read'],
        ],
        RolUsuario::Asesor->value => [
            'inmuebles' => ['read'],
            'ventas' => ['create', 'read', 'update'],
            'mensajes' => ['read', 'create'],
            'notificaciones' => ['read'],
        ],
        RolUsuario::Cliente->value => [
            'inmuebles' => ['read'],
            'reservas' => ['create', 'read', 'delete'],
            'pagos' => ['create', 'read'],
            'favoritos' => ['create', 'delete'],
            'mensajes' => ['read', 'create'],
            'notificaciones' => ['read'],
        ],
    ];

    public function run(): void
    {
        foreach (self::MATRIZ as $codigoRol => $modulos) {
            $rol = Role::where('codigo', $codigoRol)->firstOrFail();

            foreach ($modulos as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    Permiso::updateOrCreate(
                        ['rol_id' => $rol->id, 'modulo' => $modulo, 'accion' => $accion],
                        ['activo' => true],
                    );
                }
            }
        }
    }
}
