<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notas del asesor tras completar una visita (HU-12.1). Una cita tiene, como
 * mucho, una observación: se actualiza (upsert) en vez de acumular filas.
 */
class ObservacionCita extends Model
{
    protected $table = 'observacioncita';

    protected $fillable = [
        'cita_id',
        'asesor_id',
        'descripcion',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    // Crea o actualiza la observación de una cita (upsert por cita_id)
    public static function guardarPara(Cita $cita, User $asesor, string $descripcion): self
    {
        return self::updateOrCreate(
            ['cita_id' => $cita->id],
            ['asesor_id' => $asesor->id, 'descripcion' => $descripcion],
        );
    }
}
