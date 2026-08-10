<?php

namespace App\Politicas;

use App\Enumerados\EstadoVenta;
use App\Enumerados\RolUsuario;
use App\Models\User;
use App\Models\Venta;

/**
 * Las ventas las lleva el asesor que las registró; el administrador supervisa
 * todas y el cliente solo consulta las suyas.
 */
class VentaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return ! $usuario->esCliente();
    }

    public function view(User $usuario, Venta $venta): bool
    {
        return $usuario->esAdministrador()
            || $venta->asesor_id === $usuario->id
            || $venta->usuario_id === $usuario->id;
    }

    public function create(User $usuario): bool
    {
        return $usuario->tieneRol(RolUsuario::Asesor, RolUsuario::Administrador);
    }

    public function update(User $usuario, Venta $venta): bool
    {
        return $venta->estado === EstadoVenta::EnProceso
            && ($usuario->esAdministrador() || $venta->asesor_id === $usuario->id);
    }

    public function descargarEscritura(User $usuario, Venta $venta): bool
    {
        return $venta->tieneEscritura() && $this->view($usuario, $venta);
    }
}
