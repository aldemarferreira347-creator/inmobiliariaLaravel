<?php

namespace App\Servicios\Reportes;

use App\Enumerados\EstadoPago;
use App\Enumerados\TipoReporte;
use App\Models\Pago;
use Illuminate\Support\Collection;

// HU-21.1: pagos del periodo con su monto, método y estado
class ReportePagos extends Reporte
{
    public function tipo(): TipoReporte
    {
        return TipoReporte::Pagos;
    }

    public function columnas(): array
    {
        return ['Reserva', 'Cliente', 'Monto', 'Método', 'Estado', 'Fecha', 'Motivo del rechazo'];
    }

    public function columnasNumericas(): array
    {
        return [2];
    }

    public function filas(): Collection
    {
        return $this->consulta()
            ->get()
            ->map(fn (Pago $pago) => [
                $pago->reserva->codigo_reserva,
                $pago->reserva->cliente->nombre,
                (float) $pago->monto,
                $pago->metodo_pago->etiqueta(),
                $pago->estado->etiqueta(),
                $pago->created_at->format('d/m/Y H:i'),
                $pago->motivo_rechazo ?: '—',
            ]);
    }

    public function resumen(): array
    {
        $pagos = $this->consulta()->get();

        return [
            'Pagos registrados' => $pagos->count(),
            'Confirmados' => $pagos->where('estado', EstadoPago::Pagado)->count(),
            'Rechazados' => $pagos->where('estado', EstadoPago::Rechazado)->count(),
            'Total recaudado' => $this->formatearMoneda(
                $pagos->where('estado', EstadoPago::Pagado)->sum('monto')
            ),
        ];
    }

    private function consulta()
    {
        return Pago::query()
            ->with('reserva.cliente')
            ->whereBetween('created_at', [$this->filtro->inicio(), $this->filtro->fin()])
            ->when(
                EstadoPago::tryFrom((string) $this->filtro->estado),
                fn ($q, EstadoPago $estado) => $q->where('estado', $estado)
            )
            ->latest('created_at');
    }
}
