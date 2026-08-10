<?php

namespace App\Servicios;

use App\Enumerados\EstadoCita;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\TipoNotificacion;
use App\Models\Cita;
use App\Models\CitaHistorial;
use App\Models\ConfigFranjaCita;
use App\Models\Inmueble;
use App\Models\ObservacionCita;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de las citas de visita (HU-10 / HU-11 / HU-12 / HU-27).
 */
class CitaService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /**
     * El cliente solicita una visita a un inmueble en una franja válida (HU-27.1).
     *
     * @throws ValidationException
     */
    public function solicitar(Inmueble $inmueble, User $cliente, Carbon $fechaHora): Cita
    {
        $this->asegurarQueEsVisitable($inmueble, $fechaHora);

        if (Cita::query()->activaDeClientePorInmueble($cliente->id, $inmueble->id)->exists()) {
            throw ValidationException::withMessages([
                'inmueble' => 'Ya tienes una visita pendiente o asignada para este inmueble.',
            ]);
        }

        $cita = DB::transaction(function () use ($inmueble, $cliente, $fechaHora) {
            // Bloqueo pesimista: evita que dos clientes agenden la misma franja a la vez
            if (Cita::query()->ocupaFranja($inmueble->id, $fechaHora)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'hora_cita' => 'No hay horarios disponibles para esa fecha/hora. Por favor selecciona otra.',
                ]);
            }

            return Cita::create([
                'cliente_id' => $cliente->id,
                'inmueble_id' => $inmueble->id,
                'fecha' => $fechaHora,
                'estado' => EstadoCita::Pendiente,
            ]);
        });

        $this->notificaciones->paraStaff(
            'Nueva cita por asignar',
            "Se solicitó una visita para «{$inmueble->titulo}» el {$fechaHora->format('d/m/Y H:i')}. Asigna un asesor.",
            TipoNotificacion::Info,
            'cita',
            $cita->id,
        );

        return $cita;
    }

    // Asigna (o reasigna) un asesor a una cita pendiente (HU-10.1 / HU-10.3)
    public function asignar(Cita $cita, User $asesor, User $admin): void
    {
        if (! $cita->estado->admiteAsignacion()) {
            throw ValidationException::withMessages([
                'estado' => "Esta cita ya no puede reasignarse (estado: {$cita->estado->value}).",
            ]);
        }

        if (! $asesor->esAsesor() || ! $asesor->estaActivo()) {
            throw ValidationException::withMessages([
                'asesor_id' => 'No se puede asignar la cita: el asesor seleccionado no existe o está inactivo.',
            ]);
        }

        $accion = $cita->estado === EstadoCita::Asignada ? 'REASIGNADA' : 'ASIGNADA';

        DB::transaction(function () use ($cita, $asesor, $admin, $accion) {
            $cita->update(['asesor_id' => $asesor->id, 'estado' => EstadoCita::Asignada]);

            CitaHistorial::registrar($cita, $admin, $accion, "Asesor {$asesor->nombre} asignado por el administrador {$admin->nombre}.");
        });

        $this->notificaciones->paraUsuario(
            $asesor,
            'Cita asignada',
            "Se te asignó una visita para «{$cita->inmueble->titulo}» el {$cita->fecha->format('d/m/Y H:i')}.",
            TipoNotificacion::Info,
            'cita',
            $cita->id,
        );
    }

    // El asesor marca la visita como realizada y registra sus observaciones (HU-12.1)
    public function marcarRealizada(Cita $cita, User $asesor, string $observaciones): void
    {
        if ($cita->estado !== EstadoCita::Asignada) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede completar una cita que está Asignada.',
            ]);
        }

        DB::transaction(function () use ($cita, $asesor, $observaciones) {
            $cita->update(['estado' => EstadoCita::Realizada]);
            ObservacionCita::guardarPara($cita, $asesor, $observaciones);
            CitaHistorial::registrar($cita, $asesor, 'REALIZADA', 'Visita marcada como realizada por el asesor.');
        });

        $this->notificaciones->paraStaff(
            'Visita completada',
            "Se registraron observaciones de la visita a «{$cita->inmueble->titulo}».",
            TipoNotificacion::Exito,
            'cita',
            $cita->id,
        );

        $this->notificaciones->paraUsuario(
            $cita->cliente,
            'Visita completada',
            "Tu visita a «{$cita->inmueble->titulo}» fue completada. Ya puedes consultar las observaciones.",
            TipoNotificacion::Exito,
            'cita',
            $cita->id,
        );
    }

    // El asesor propietario corrige el texto de una observación ya registrada
    public function editarObservacion(Cita $cita, User $asesor, string $observaciones): void
    {
        DB::transaction(function () use ($cita, $asesor, $observaciones) {
            ObservacionCita::guardarPara($cita, $asesor, $observaciones);
            CitaHistorial::registrar($cita, $asesor, 'OBSERVACION_EDITADA', 'El asesor actualizó las observaciones de la visita.');
        });
    }

    // El cliente cancela su propia visita antes de que se realice (HU-27.4)
    public function cancelar(Cita $cita, User $cliente): void
    {
        DB::transaction(function () use ($cita, $cliente) {
            $cita->update(['estado' => EstadoCita::Cancelada]);
            CitaHistorial::registrar($cita, $cliente, 'CANCELADA', 'El cliente canceló la cita.');
        });

        if ($cita->asesor_id !== null) {
            $this->notificaciones->paraUsuario(
                $cita->asesor,
                'Cita cancelada',
                "El cliente canceló la visita a «{$cita->inmueble->titulo}».",
                TipoNotificacion::Aviso,
                'cita',
                $cita->id,
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueEsVisitable(Inmueble $inmueble, Carbon $fechaHora): void
    {
        if ($inmueble->estado === EstadoInmueble::Ocupado) {
            throw ValidationException::withMessages([
                'inmueble' => 'Este inmueble ya no está disponible para visitas.',
            ]);
        }

        if (! ConfigFranjaCita::esHoraValida($fechaHora)) {
            throw ValidationException::withMessages([
                'hora_cita' => 'No hay horarios disponibles para esa fecha/hora. Por favor selecciona otra.',
            ]);
        }
    }
}
