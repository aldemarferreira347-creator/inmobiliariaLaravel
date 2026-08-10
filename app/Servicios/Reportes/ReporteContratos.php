<?php

namespace App\Servicios\Reportes;

use App\Enumerados\EstadoContrato;
use App\Enumerados\TipoReporte;
use App\Models\Contrato;
use Illuminate\Support\Collection;

/**
 * HU-21.2: contratos con su inmueble, cliente, valor mensual y fecha de fin.
 * A diferencia del prototipo, el estado y el rango de fechas sí se aplican.
 */
class ReporteContratos extends Reporte
{
    public function tipo(): TipoReporte
    {
        return TipoReporte::Contratos;
    }

    public function columnas(): array
    {
        return ['N.º contrato', 'Inmueble', 'Cliente', 'Valor mensual', 'Inicio', 'Fin', 'Estado'];
    }

    public function columnasNumericas(): array
    {
        return [3];
    }

    public function filas(): Collection
    {
        return $this->consulta()
            ->get()
            ->map(fn (Contrato $contrato) => [
                $contrato->numero_contrato,
                $contrato->reserva->inmueble->titulo,
                $contrato->reserva->cliente->nombre,
                (float) $contrato->valor_mensual,
                $contrato->fecha_inicio->format('d/m/Y'),
                $contrato->fecha_fin?->format('d/m/Y') ?? 'Indefinida',
                $contrato->estado->etiqueta(),
            ]);
    }

    public function resumen(): array
    {
        $contratos = $this->consulta()->get();

        return [
            'Contratos' => $contratos->count(),
            'Vigentes' => $contratos->where('estado', EstadoContrato::Vigente)->count(),
            'Vencidos' => $contratos->where('estado', EstadoContrato::Vencido)->count(),
            'Renta mensual vigente' => $this->formatearMoneda(
                $contratos->where('estado', EstadoContrato::Vigente)->sum('valor_mensual')
            ),
        ];
    }

    private function consulta()
    {
        return Contrato::query()
            ->with('reserva.inmueble', 'reserva.cliente')
            ->whereBetween('created_at', [$this->filtro->inicio(), $this->filtro->fin()])
            ->when(
                EstadoContrato::tryFrom((string) $this->filtro->estado),
                fn ($q, EstadoContrato $estado) => $q->where('estado', $estado)
            )
            ->orderBy('fecha_fin');
    }
}
