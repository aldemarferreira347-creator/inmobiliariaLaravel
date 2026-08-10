<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría de cambios en la asignación/estado de una cita.
 * Separada de ObservacionCita, que guarda las notas humanas de la visita.
 */
class CitaHistorial extends Model
{
    protected $table = 'cita_historial';

    public const UPDATED_AT = null;

    protected $fillable = [
        'cita_id',
        'usuario_id',
        'accion',
        'descripcion',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Registra un evento de auditoría (ASIGNADA, REASIGNADA, REALIZADA, CANCELADA...)
    public static function registrar(Cita $cita, User $autor, string $accion, string $descripcion): self
    {
        return self::create([
            'cita_id' => $cita->id,
            'usuario_id' => $autor->id,
            'accion' => mb_strtoupper($accion),
            'descripcion' => $descripcion,
        ]);
    }
}
