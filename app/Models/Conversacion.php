<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hilo de conversación entre un cliente y un asesor sobre un inmueble (HU-13).
 * Solo participan esos dos usuarios; el administrador puede consultar el hilo
 * pero no forma parte de él.
 */
class Conversacion extends Model
{
    protected $table = 'conversacion';

    protected $fillable = ['cliente_id', 'asesor_id', 'inmueble_id', 'ultimo_mensaje_en'];

    protected function casts(): array
    {
        return ['ultimo_mensaje_en' => 'datetime'];
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

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'conversacion_id');
    }

    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(Mensaje::class, 'conversacion_id')->latestOfMany();
    }

    // ----------------------------------------------------------------
    // Consultas
    // ----------------------------------------------------------------

    public function scopeDe(Builder $query, User $usuario): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('cliente_id', $usuario->id)
            ->orWhere('asesor_id', $usuario->id));
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('ultimo_mensaje_en')->orderByDesc('id');
    }

    // ----------------------------------------------------------------
    // Participantes
    // ----------------------------------------------------------------

    public function participa(User $usuario): bool
    {
        return in_array($usuario->id, [$this->cliente_id, $this->asesor_id], true);
    }

    // El interlocutor del usuario dado dentro del hilo
    public function interlocutorDe(User $usuario): User
    {
        return $usuario->id === $this->cliente_id ? $this->asesor : $this->cliente;
    }

    public function mensajesSinLeerPara(User $usuario): int
    {
        return $this->mensajes()
            ->whereNull('leido_en')
            ->where('emisor_id', '!=', $usuario->id)
            ->count();
    }
}
