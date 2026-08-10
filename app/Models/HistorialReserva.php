<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * Registro de auditoría de los cambios de estado de una reserva.
 *
 * Es una tabla de solo inserción: no se modifica ni se elimina. Un
 * `cambiado_por` nulo identifica los cambios originados por el sistema.
 */
class HistorialReserva extends Model
{
    protected $table = 'historial_reserva';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'reserva_id',
        'estado_anterior',
        'estado_nuevo',
        'cambiado_por',
        'comentario',
        'ip_origen',
    ];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }

    // Deja constancia de una transición, tomando la IP de la petición en curso
    public static function registrar(
        Reserva $reserva,
        ?string $estadoAnterior,
        string $estadoNuevo,
        string $comentario,
        ?int $autorId = null,
    ): self {
        return static::create([
            'reserva_id' => $reserva->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'cambiado_por' => $autorId,
            'comentario' => $comentario,
            'ip_origen' => Request::ip(),
        ]);
    }

    public function getAutorNombreAttribute(): string
    {
        return $this->autor?->nombre ?? 'Sistema';
    }
}
