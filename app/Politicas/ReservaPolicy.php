<?php

namespace App\Politicas;

use App\Models\Reserva;
use App\Models\User;

/**
 * Quién puede ver y mover una reserva.
 * El cliente solo alcanza las suyas; el equipo interno las ve todas.
 */
class ReservaPolicy
{
    public function view(User $usuario, Reserva $reserva): bool
    {
        return $reserva->usuario_id === $usuario->id || ! $usuario->esCliente();
    }

    public function create(User $usuario): bool
    {
        return $usuario->esCliente();
    }

    // El cliente retira su reserva mientras no haya registrado el pago
    public function cancelar(User $usuario, Reserva $reserva): bool
    {
        if ($usuario->esAdministrador()) {
            return ! $reserva->estado->esFinal();
        }

        return $reserva->usuario_id === $usuario->id
            && $reserva->estado->admiteCancelacionDelCliente();
    }

    public function registrarPago(User $usuario, Reserva $reserva): bool
    {
        return $reserva->usuario_id === $usuario->id && $reserva->admiteNuevoPago();
    }

    public function revisarPago(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }
}
