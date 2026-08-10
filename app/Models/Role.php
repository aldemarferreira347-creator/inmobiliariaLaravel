<?php

namespace App\Models;

use App\Enumerados\RolUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Catálogo de roles (tabla `rol`)
class Role extends Model
{
    protected $table = 'rol';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    protected function casts(): array
    {
        return ['codigo' => RolUsuario::class];
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class, 'rol_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol', 'codigo');
    }
}
