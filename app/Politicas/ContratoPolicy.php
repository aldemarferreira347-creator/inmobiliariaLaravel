<?php

namespace App\Politicas;

use App\Enumerados\EstadoContrato;
use App\Models\Contrato;
use App\Models\User;

/**
 * Los contratos los gestiona el administrador; el cliente solo puede consultar
 * y descargar el suyo.
 */
class ContratoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    public function view(User $usuario, Contrato $contrato): bool
    {
        return $usuario->esAdministrador() || $contrato->reserva->usuario_id === $usuario->id;
    }

    public function create(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    public function update(User $usuario, Contrato $contrato): bool
    {
        return $usuario->esAdministrador();
    }

    public function rescindir(User $usuario, Contrato $contrato): bool
    {
        return $usuario->esAdministrador() && $contrato->estado === EstadoContrato::Vigente;
    }

    // Descarga del PDF firmado: el dueño de la reserva o el administrador
    public function descargar(User $usuario, Contrato $contrato): bool
    {
        return $contrato->tieneArchivo() && $this->view($usuario, $contrato);
    }
}
