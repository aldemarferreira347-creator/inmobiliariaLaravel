<?php

namespace App\Models;

use App\Enumerados\EstadoCita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Configuración de franjas horarias para agendar citas, una fila por día de
 * la semana (RF-26.2 / HU-27.1/27.2). Un día sin fila (p. ej. Domingo, si
 * nunca se activó) simplemente no tiene horarios disponibles.
 */
class ConfigFranjaCita extends Model
{
    protected $table = 'config_franja_cita';

    public const CREATED_AT = null;

    public const UPDATED_AT = 'actualizado_en';

    /** Días admitidos por la columna `dia_semana` */
    public const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

    // Orden de exhibición para el panel admin (semana empezando en Lunes)
    public const DIAS_SEMANA = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];

    protected $fillable = [
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'intervalo_minutos',
        'activo',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'intervalo_minutos' => 'integer',
            'actualizado_en' => 'datetime',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    // Crea o actualiza la franja de un día (uk sobre dia_semana evita duplicados)
    public static function guardarPara(string $diaSemana, string $horaInicio, string $horaFin, int $intervaloMinutos, bool $activo, ?User $admin): self
    {
        return self::updateOrCreate(
            ['dia_semana' => $diaSemana],
            [
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'intervalo_minutos' => $intervaloMinutos,
                'activo' => $activo,
                'actualizado_por' => $admin?->id,
            ],
        );
    }

    // Franja activa del día de la semana de una fecha dada, o null si no hay ninguna configurada
    public static function delDia(Carbon $fecha): ?self
    {
        return self::query()
            ->where('dia_semana', self::DIAS[(int) $fecha->format('w')])
            ->where('activo', true)
            ->first();
    }

    /**
     * Horas disponibles (H:i) para un inmueble en una fecha, respetando la
     * franja configurada y las citas activas ya agendadas (HU-27.1/27.2).
     *
     * @return array<int, string>
     */
    public static function disponiblesPara(int $inmuebleId, string $fechaYmd): array
    {
        $fecha = Carbon::createFromFormat('Y-m-d', $fechaYmd)?->startOfDay();
        $franja = $fecha ? self::delDia($fecha) : null;

        if (! $franja) {
            return [];
        }

        $ocupadas = Cita::query()
            ->where('inmueble_id', $inmuebleId)
            ->whereDate('fecha', $fechaYmd)
            ->whereIn('estado', [EstadoCita::Pendiente, EstadoCita::Asignada])
            ->get()
            ->map(fn (Cita $cita) => $cita->fecha->format('H:i'))
            ->all();

        $horas = [];
        $cursor = Carbon::createFromFormat('H:i:s', $franja->hora_inicio);
        $fin = Carbon::createFromFormat('H:i:s', $franja->hora_fin);

        while ($cursor->lt($fin)) {
            $hora = $cursor->format('H:i');
            if (! in_array($hora, $ocupadas, true)) {
                $horas[] = $hora;
            }
            $cursor->addMinutes($franja->intervalo_minutos);
        }

        return $horas;
    }

    // Valida que una hora concreta caiga exactamente sobre una franja permitida
    public static function esHoraValida(Carbon $fechaHora): bool
    {
        $franja = self::delDia($fechaHora);
        if (! $franja) {
            return false;
        }

        $hora = $fechaHora->format('H:i:s');
        if ($hora < $franja->hora_inicio || $hora >= $franja->hora_fin) {
            return false;
        }

        $minutosDesdeInicio = (Carbon::createFromFormat('H:i:s', $hora)->diffInMinutes(Carbon::createFromFormat('H:i:s', $franja->hora_inicio), true));

        return $minutosDesdeInicio % $franja->intervalo_minutos === 0;
    }
}
