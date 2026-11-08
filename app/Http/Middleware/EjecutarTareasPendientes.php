<?php

namespace App\Http\Middleware;

use Closure;
use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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

    public const ARCHIVOS_POR_PODA = 500;

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

        try {
            $this->podarCacheDeArchivos();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * La caché en archivos solo borra una entrada vencida cuando alguien la lee:
     * sin cron las claves de un solo uso (límites de intentos, reintentos) se
     * acumularían. Revisa como mucho ARCHIVOS_POR_PODA archivos por ejecución,
     * empezando por subcarpetas al azar para cubrirlas todas con el tiempo.
     */
    private function podarCacheDeArchivos(): void
    {
        $almacen = config('cache.default');
        $ruta = config("cache.stores.{$almacen}.path");

        if (config("cache.stores.{$almacen}.driver") !== 'file' || ! is_string($ruta) || ! is_dir($ruta)) {
            return;
        }

        $carpetas = glob($ruta.'/*', GLOB_ONLYDIR) ?: [];
        shuffle($carpetas);
        $revisados = 0;

        foreach ($carpetas as $carpeta) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS)) as $archivo) {
                if (++$revisados > self::ARCHIVOS_POR_PODA) {
                    return;
                }

                // FileStore guarda al inicio de cada archivo la expiración (10 dígitos, Unix)
                $expira = @file_get_contents($archivo->getPathname(), false, null, 0, 10);
                if (is_string($expira) && ctype_digit($expira) && (int) $expira <= now()->timestamp) {
                    @unlink($archivo->getPathname());
                }
            }
        }
    }
}
