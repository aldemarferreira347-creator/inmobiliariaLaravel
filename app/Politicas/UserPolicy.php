<?php

namespace App\Politicas;

use App\Models\User;

/**
 * Reglas de la gestión de usuarios (HU-16 / HU-26).
 *
 * Todas las acciones son exclusivas del administrador, que además nunca puede
 * actuar sobre su propia cuenta: cambiarse el rol, desactivarse o eliminarse
 * lo dejaría fuera del panel sin forma de volver a entrar.
 */
class UserPolicy
{
    public function gestionar(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    public function cambiarRol(User $usuario, User $objetivo): bool
    {
        return $usuario->esAdministrador() && ! $objetivo->esElMismoQue($usuario);
    }

    public function cambiarEstado(User $usuario, User $objetivo): bool
    {
        return $usuario->esAdministrador() && ! $objetivo->esElMismoQue($usuario);
    }

    public function delete(User $usuario, User $objetivo): bool
    {
        return $usuario->esAdministrador() && ! $objetivo->esElMismoQue($usuario);
    }
}
