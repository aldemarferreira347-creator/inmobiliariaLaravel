<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permiso de un rol sobre un módulo (RF-25.4 / CU-20).
 *
 * Es un catálogo informativo que se muestra en /admin/permisos. La autorización
 * efectiva la aplican el middleware `rol` y las policies, no esta tabla.
 */
class Permiso extends Model
{
    protected $table = 'permiso';

    public $timestamps = false;

    protected $fillable = ['rol_id', 'modulo', 'accion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    public function getAccionEtiquetaAttribute(): string
    {
        return match ($this->accion) {
            'create' => 'Crear',
            'read' => 'Consultar',
            'update' => 'Editar',
            'delete' => 'Eliminar',
            default => ucfirst($this->accion),
        };
    }
}
