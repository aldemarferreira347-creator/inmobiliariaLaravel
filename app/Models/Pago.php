<?php

namespace App\Models;

use App\Enumerados\EstadoPago;
use App\Enumerados\MetodoPago;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Pago asociado a una reserva (HU-21.1 / HU-23)
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;

    protected $table = 'pago';

    protected $fillable = [
        'reserva_id',
        'revisado_por',
        'metodo_pago',
        'moneda',
        'monto',
        'referencia',
        'referencia_pasarela',
        'estado',
        'motivo_rechazo',
        'revisado_en',
    ];

    protected function casts(): array
    {
        return [
            'metodo_pago' => MetodoPago::class,
            'estado' => EstadoPago::class,
            'monto' => 'decimal:2',
            'revisado_en' => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function scopeConfirmados(Builder $query): Builder
    {
        return $query->where('estado', EstadoPago::Pagado);
    }

    public function scopeEnRevision(Builder $query): Builder
    {
        return $query->whereIn('estado', [EstadoPago::Pendiente, EstadoPago::Procesando]);
    }

    public function getMontoFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->monto, 0, ',', '.');
    }
}
