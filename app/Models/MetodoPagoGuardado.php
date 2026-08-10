<?php

namespace App\Models;

use App\Enumerados\PasarelaPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarjeta tokenizada por el cliente (HU-20). El número completo, el CVV y la
 * fecha de expiración exacta nunca pasan por aquí: los maneja la pasarela.
 */
class MetodoPagoGuardado extends Model
{
    protected $table = 'metodo_pago_guardado';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'cliente_id',
        'pasarela',
        'token_pasarela',
        'cliente_pasarela_id',
        'marca',
        'ultimos_4',
        'nombre_titular',
        'mes_expiracion',
        'anio_expiracion',
        'predeterminado',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'pasarela' => PasarelaPago::class,
            'mes_expiracion' => 'integer',
            'anio_expiracion' => 'integer',
            'predeterminado' => 'boolean',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderByDesc('predeterminado')->orderByDesc('creado_en');
    }

    public function getDescripcionAttribute(): string
    {
        return trim("{$this->marca} •••• {$this->ultimos_4}");
    }
}
