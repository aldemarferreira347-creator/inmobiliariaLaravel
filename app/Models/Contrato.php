<?php

namespace App\Models;

use App\Enumerados\EstadoContrato;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrato de arriendo asociado a una reserva confirmada (HU-17).
 * Un contrato vigente mantiene el inmueble Ocupado.
 */
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use HasFactory;

    /** Días naturales tras la confirmación de la reserva para emitir el contrato (RN-18) */
    public const DIAS_PARA_EMITIR = 7;

    protected $table = 'contrato';

    protected $fillable = [
        'reserva_id',
        'numero_contrato',
        'fecha_inicio',
        'fecha_fin',
        'valor_mensual',
        'archivo_ruta',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoContrato::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'valor_mensual' => 'decimal:2',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', EstadoContrato::Vigente);
    }

    // Contratos vigentes cuya fecha de fin ya pasó, pendientes de marcar (HU-17.3)
    public function scopePorVencer(Builder $query): Builder
    {
        return $query
            ->where('estado', EstadoContrato::Vigente)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', now());
    }

    public static function generarNumero(Reserva $reserva): string
    {
        return sprintf('CON-%s-%05d', now()->year, $reserva->id);
    }

    public function tieneArchivo(): bool
    {
        return filled($this->archivo_ruta);
    }

    public function getVigenciaAttribute(): string
    {
        $inicio = $this->fecha_inicio->format('d/m/Y');
        $fin = $this->fecha_fin?->format('d/m/Y') ?? 'Indefinida';

        return "{$inicio} — {$fin}";
    }

    public function getValorFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->valor_mensual, 0, ',', '.');
    }
}
