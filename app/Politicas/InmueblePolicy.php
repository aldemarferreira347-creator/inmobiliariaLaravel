<?php

namespace App\Politicas;

use App\Models\Inmueble;
use App\Models\User;

/**
 * El catálogo es público, pero solo el administrador da de alta, edita o
 * elimina inmuebles (HU-04).
 */
class InmueblePolicy
{
    public function create(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    public function update(User $usuario, Inmueble $inmueble): bool
    {
        return $usuario->esAdministrador();
    }

    public function delete(User $usuario, Inmueble $inmueble): bool
    {
        return $usuario->esAdministrador();
    }
}
