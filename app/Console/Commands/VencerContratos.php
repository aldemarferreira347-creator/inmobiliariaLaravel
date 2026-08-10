<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Servicios\ContratoService;
use Illuminate\Console\Command;
use Throwable;

/**
 * HU-17.3: marca como vencidos los contratos cuya fecha de fin ya pasó y
 * devuelve sus inmuebles al catálogo. Se ejecuta cada hora.
 */
class VencerContratos extends Command
{
    protected $signature = 'contratos:vencer';

    protected $description = 'Marca como vencidos los contratos caducados y libera sus inmuebles';

    public function handle(ContratoService $contratos): int
    {
        $caducados = Contrato::porVencer()->with('reserva.inmueble')->get();
        $vencidos = 0;

        foreach ($caducados as $contrato) {
            try {
                $contratos->vencer($contrato);
                $vencidos++;
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo vencer el contrato {$contrato->numero_contrato}: {$e->getMessage()}");
            }
        }

        $this->info("Contratos vencidos: {$vencidos} de {$caducados->count()}.");

        return self::SUCCESS;
    }
}
