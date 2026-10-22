<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /**
     * Ejecuta `$accion` la primera vez que llega el evento; false si ya se procesó.
     *
     * La fila del evento se bloquea durante el procesamiento y solo se marca
     * como procesada si `$accion` termina bien: si falla, la transacción se
     * revierte, la excepción sube (Stripe recibe un 500) y el reintento de
     * Stripe vuelve a aplicarlo en vez de perderse.
     */
    public static function procesarUnaVez(string $pasarela, string $eventoId, string $tipo, Closure $accion): bool
    {
        self::firstOrCreate(['pasarela' => $pasarela, 'evento_id' => $eventoId], ['tipo' => $tipo]);

        return DB::transaction(function () use ($pasarela, $eventoId, $accion) {
            $evento = self::query()
                ->where('pasarela', $pasarela)
                ->where('evento_id', $eventoId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($evento->procesado_en !== null) {
                return false;
            }

            $accion();

            $evento->update(['procesado_en' => now()]);

            return true;
        });
    }
}
