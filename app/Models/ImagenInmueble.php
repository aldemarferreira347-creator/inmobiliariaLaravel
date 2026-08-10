<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// Imagen de la galería de un inmueble (HU-08)
class ImagenInmueble extends Model
{
    protected $table = 'imageninmueble';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = ['inmueble_id', 'url', 'es_principal', 'orden'];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'orden' => 'integer',
            'creado_en' => 'datetime',
        ];
    }

    public function inmueble(): BelongsTo
    {
        return $this->belongsTo(Inmueble::class, 'inmueble_id');
    }

    public function getUrlPublicaAttribute(): string
    {
        return Str::startsWith($this->url, ['http://', 'https://']) ? $this->url : asset($this->url);
    }
}
