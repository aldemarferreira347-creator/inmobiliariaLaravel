<?php

namespace App\Politicas;

use App\Models\Cita;
use App\Models\User;

/**
 * Quién puede ver, solicitar y mover una cita (HU-10 / HU-11 / HU-12 / HU-27).
 * El equipo interno (admin/asesor) se filtra con el middleware 'rol' en
 * routes/web.php; esta policy resuelve el acceso a una cita puntual.
 */
class CitaPolicy
{
    public function view(User $usuario, Cita $cita): bool
    {
        if ($usuario->esAdministrador()) {
            return true;
        }

        if ($usuario->esAsesor()) {
            return $cita->asesor_id === $usuario->id;
        }

        return $cita->cliente_id === $usuario->id;
    }

    public function create(User $usuario): bool
    {
        return $usuario->esCliente();
    }

    public function asignar(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    public function configurarFranjas(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    // El cliente retira su propia visita mientras siga viva
    public function cancelar(User $usuario, Cita $cita): bool
    {
        return $cita->cliente_id === $usuario->id && $cita->estado->admiteCancelacion();
    }

    // Solo el asesor asignado registra o edita las observaciones de la visita
    public function registrarObservacion(User $usuario, Cita $cita): bool
    {
        return $cita->asesor_id === $usuario->id;
    }
}
