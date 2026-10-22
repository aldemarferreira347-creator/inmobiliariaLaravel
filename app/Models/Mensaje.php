<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// Mensaje dentro de una conversación (HU-13)
class Mensaje extends Model
{
    protected $table = 'mensaje';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = ['conversacion_id', 'emisor_id', 'contenido', 'adjunto_url', 'leido_en'];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'leido_en' => 'datetime',
        ];
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class, 'conversacion_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    public function tieneAdjunto(): bool
    {
        return filled($this->adjunto_url);
    }

    // Los adjuntos anteriores vivían en el disco público (prefijo `storage/`); los nuevos son privados
    public function adjuntoEsPrivado(): bool
    {
        return $this->tieneAdjunto() && ! Str::startsWith($this->adjunto_url, 'storage/');
    }

    // Solo para adjuntos heredados del disco público; los privados se piden por ruta autorizada
    public function getAdjuntoPublicoAttribute(): ?string
    {
        return $this->tieneAdjunto() && ! $this->adjuntoEsPrivado() ? asset($this->adjunto_url) : null;
    }

    // Texto abreviado para la lista de conversaciones
    public function getResumenAttribute(): string
    {
        if (blank($this->contenido)) {
            return 'Imagen adjunta';
        }

        return Str::limit($this->contenido, 42);
    }
}
