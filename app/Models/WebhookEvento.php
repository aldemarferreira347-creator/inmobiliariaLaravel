<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de idempotencia para eventos de webhook ya procesados (HU-23):
 * evita que un mismo evento reenviado por la pasarela se aplique dos veces.
 */
class WebhookEvento extends Model
{
    protected $table = 'webhook_evento';

    protected $fillable = [
        'pasarela',
        'evento_id',
        'tipo',
        'procesado_en',
    ];

    protected function casts(): array
    {
        return [
            'procesado_en' => 'datetime',
        ];
    }

    // Registra el evento si es la primera vez que se ve; false si ya se había procesado
    public static function registrarSiEsNuevo(string $pasarela, string $eventoId, string $tipo): bool
    {
        $evento = self::firstOrCreate(
            ['pasarela' => $pasarela, 'evento_id' => $eventoId],
            ['tipo' => $tipo],
        );

        if ($evento->procesado_en !== null) {
            return false;
        }

        $evento->update(['procesado_en' => now()]);

        return true;
    }
}
