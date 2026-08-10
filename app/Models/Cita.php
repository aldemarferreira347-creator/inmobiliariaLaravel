<?php

namespace App\Models;

use App\Enumerados\EstadoCita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita de visita a un inmueble (HU-10 / HU-11 / HU-12 / HU-27).
 * Las reglas de negocio y las transiciones de estado viven en CitaService.
 */
class Cita extends Model
{
    protected $table = 'cita';

    protected $fillable = [
        'cliente_id',
        'asesor_id',
        'inmueble_id',
        'fecha',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'estado' => EstadoCita::class,
        ];
    }

    // ----------------------------------------------------------------
    // Relaciones
    // ----------------------------------------------------------------

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function inmueble(): BelongsTo
    {
        return $this->belongsTo(Inmueble::class, 'inmueble_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(CitaHistorial::class, 'cita_id')->latest('created_at');
    }

    public function observacion(): HasOne
    {
        return $this->hasOne(ObservacionCita::class, 'cita_id');
    }

    // ----------------------------------------------------------------
    // Consultas reutilizables
    // ----------------------------------------------------------------

    public function scopeSinAsignar(Builder $query): Builder
    {
        return $query->whereNull('asesor_id')->where('estado', EstadoCita::Pendiente);
    }

    public function scopeDeAsesor(Builder $query, int $asesorId): Builder
    {
        return $query->where('asesor_id', $asesorId);
    }

    public function scopeProximas(Builder $query): Builder
    {
        return $query->orderBy('fecha');
    }

    // Impide que un mismo cliente tenga dos visitas activas para el mismo inmueble
    public function scopeActivaDeClientePorInmueble(Builder $query, int $clienteId, int $inmuebleId): Builder
    {
        return $query->where('cliente_id', $clienteId)
            ->where('inmueble_id', $inmuebleId)
            ->whereIn('estado', [EstadoCita::Pendiente, EstadoCita::Asignada]);
    }

    // Evita que dos clientes distintos reserven la misma franja para el mismo inmueble
    public function scopeOcupaFranja(Builder $query, int $inmuebleId, \DateTimeInterface $fecha): Builder
    {
        return $query->where('inmueble_id', $inmuebleId)
            ->where('fecha', $fecha)
            ->whereIn('estado', [EstadoCita::Pendiente, EstadoCita::Asignada]);
    }

    public function tieneObservacion(): bool
    {
        return $this->observacion !== null;
    }
}
