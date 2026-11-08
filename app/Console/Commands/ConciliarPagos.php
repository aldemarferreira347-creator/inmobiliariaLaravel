<?php

namespace App\Console\Commands;

use App\Enumerados\EstadoPago;
use App\Models\Pago;
use App\Servicios\StripeCardService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Concilia con Stripe los pagos con tarjeta que quedaron en revisión (p. ej.
 * el cliente autenticó 3DS y cerró la pestaña). Sustituye al webhook, que
 * InfinityFree no deja llegar.
 */
class ConciliarPagos extends Command
{
    protected $signature = 'pagos:conciliar';

    protected $description = 'Consulta a Stripe el resultado de los pagos con tarjeta que siguen en revisión';

    public function handle(StripeCardService $stripe): int
    {
        $pendientes = Pago::where('estado', EstadoPago::Procesando)
            ->whereNotNull('referencia_pasarela')
            ->where('created_at', '<', now()->subMinutes(10))
            ->get();
        $conciliados = 0;

        foreach ($pendientes as $pago) {
            try {
                $stripe->sincronizarPago($pago);
                $conciliados++;
            } catch (Throwable $e) {
                // Error de red u otro: el pago queda igual y se reintenta en la próxima ejecución
                report($e);
                $this->error("No se pudo conciliar el pago {$pago->id}: {$e->getMessage()}");
            }
        }

        $this->info("Pagos conciliados: {$conciliados} de {$pendientes->count()}.");

        return self::SUCCESS;
    }
}
