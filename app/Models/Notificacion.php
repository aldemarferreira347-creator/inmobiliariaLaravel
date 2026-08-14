<?php

namespace App\Models;

use App\Enumerados\TipoNotificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Notificación in-app dirigida a un usuario (HU-15 / HU-22)
class Notificacion extends Model
{
    protected $table = 'notificacion';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'titulo',
        'mensaje',
        'tipo',
        'referencia_tipo',
        'referencia_id',
        'leida_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacion::class,
            'creado_en' => 'datetime',
            'leida_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeSinLeer(Builder $query): Builder
    {
        return $query->whereNull('leida_en');
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('creado_en')->orderByDesc('id');
    }

    public function estaLeida(): bool
    {
        return $this->leida_en !== null;
    }
}
