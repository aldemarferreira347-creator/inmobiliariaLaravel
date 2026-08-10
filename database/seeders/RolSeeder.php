<?php

namespace Database\Seeders;

use App\Enumerados\RolUsuario;
use App\Models\Role;
use Illuminate\Database\Seeder;

// Catálogo de roles del sistema
class RolSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RolUsuario::cases() as $rol) {
            Role::updateOrCreate(
                ['codigo' => $rol->value],
                ['nombre' => $rol->etiqueta(), 'descripcion' => $rol->descripcion()],
            );
        }
    }
}
