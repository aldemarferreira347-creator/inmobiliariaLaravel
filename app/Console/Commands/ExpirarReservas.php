<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use App\Servicios\ReservaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * HU-09.2: libera los inmuebles de las reservas que agotaron su plazo de pago.
 * Se ejecuta cada hora (ver routes/console.php).
 */
class ExpirarReservas extends Command
{
    protected $signature = 'reservas:expirar';

    protected $description = 'Expira las reservas cuyo plazo de pago venció y libera sus inmuebles';

    public function handle(ReservaService $reservas): int
    {
        $vencidas = Reserva::vencidas()->with('inmueble')->get();
        $expiradas = 0;

        // Cada reserva se procesa por separado: un fallo aislado no detiene al resto
        foreach ($vencidas as $reserva) {
            try {
                $reservas->expirar($reserva);
                $expiradas++;
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo expirar la reserva {$reserva->codigo_reserva}: {$e->getMessage()}");
            }
        }

        $this->info("Reservas expiradas: {$expiradas} de {$vencidas->count()}.");

        return self::SUCCESS;
    }
}
