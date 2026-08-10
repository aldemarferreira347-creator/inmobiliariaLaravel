<?php

namespace App\Models;

use App\Enumerados\EstadoVenta;
use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Venta de un inmueble gestionada por un asesor (HU-14)
class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $table = 'venta';

    protected $fillable = [
        'inmueble_id',
        'usuario_id',
        'asesor_id',
        'precio_venta',
        'fecha_venta',
        'notaria',
        'escritura_ruta',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoVenta::class,
            'precio_venta' => 'decimal:2',
            'fecha_venta' => 'date',
        ];
    }

    public function inmueble(): BelongsTo
    {
        return $this->belongsTo(Inmueble::class, 'inmueble_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function scopeEnProceso(Builder $query): Builder
    {
        return $query->where('estado', EstadoVenta::EnProceso);
    }

    public function tieneEscritura(): bool
    {
        return filled($this->escritura_ruta);
    }

    public function getPrecioFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->precio_venta, 0, ',', '.');
    }

    public function getAsesorNombreAttribute(): string
    {
        return $this->asesor?->nombre ?? 'Sin asignar';
    }
}
