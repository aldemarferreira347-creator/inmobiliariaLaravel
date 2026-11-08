<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Planificador por petición (HU-09.2 / HU-17.3 / pagos 3DS).
 *
 * InfinityFree no ofrece cron ni cola ni webhooks entrantes, así que las
 * tareas periódicas se ejecutan al terminar una petición web, como mucho una
 * vez cada 15 minutos. Corre en terminate(): el usuario ya recibió su página.
 */
class EjecutarTareasPendientes
{
    public const SEGUNDOS_ENTRE_EJECUCIONES = 900;

    public const TAREAS = ['reservas:expirar', 'contratos:vencer', 'pagos:conciliar'];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        return $siguiente($peticion);
    }

    public function terminate(Request $peticion, Response $respuesta): void
    {
        if (! config('app.tareas_por_peticion') || $peticion->is('stripe/webhook')) {
            return;
        }

        try {
            // add() es atómico: solo una petición obtiene el turno
            if (! Cache::add('tareas:ultima-ejecucion', now()->timestamp, self::SEGUNDOS_ENTRE_EJECUCIONES)) {
                return;
            }
        } catch (Throwable $e) {
            report($e);

            return;
        }

        foreach (self::TAREAS as $tarea) {
            try {
                Artisan::call($tarea);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
