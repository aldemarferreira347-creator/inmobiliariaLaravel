<?php

namespace App\Politicas;

use App\Models\Conversacion;
use App\Models\User;

/**
 * Los hilos son privados entre el cliente y su asesor.
 * El administrador puede leerlos para supervisar la atención, y responder,
 * pero no se cuenta como parte del hilo.
 */
class ConversacionPolicy
{
    public function view(User $usuario, Conversacion $conversacion): bool
    {
        return $conversacion->participa($usuario) || $usuario->esAdministrador();
    }

    public function responder(User $usuario, Conversacion $conversacion): bool
    {
        return $this->view($usuario, $conversacion);
    }
}
