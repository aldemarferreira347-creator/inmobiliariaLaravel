<?php

namespace App\Servicios\Reportes;

use App\Enumerados\EstadoReserva;
use App\Enumerados\TipoReporte;
use App\Models\Reserva;
use Illuminate\Support\Collection;

// HU-06: reservaciones del periodo con su inmueble, cliente, monto y estado
class ReporteReservaciones extends Reporte
{
    public function tipo(): TipoReporte
    {
        return TipoReporte::Reservaciones;
    }

    public function columnas(): array
    {
        return ['Código', 'Fecha', 'Inmueble', 'Cliente', 'Monto', 'Estado'];
    }

    public function columnasNumericas(): array
    {
        return [4];
    }

    public function filas(): Collection
    {
        return $this->consulta()
            ->get()
            ->map(fn (Reserva $reserva) => [
                $reserva->codigo_reserva,
                $reserva->created_at->format('d/m/Y H:i'),
                $reserva->inmueble->titulo,
                $reserva->cliente->nombre,
                (float) $reserva->monto_reserva,
                $reserva->estado->etiqueta(),
            ]);
    }

    public function resumen(): array
    {
        $reservas = $this->consulta()->get();

        return [
            'Total de reservas' => $reservas->count(),
            'Confirmadas' => $reservas->where('estado', EstadoReserva::Confirmada)->count(),
            'Pendientes de pago' => $reservas->where('estado', EstadoReserva::PendientePago)->count(),
            'Monto reservado' => $this->formatearMoneda(
                $reservas->where('estado', EstadoReserva::Confirmada)->sum('monto_reserva')
            ),
        ];
    }

    private function consulta()
    {
        return Reserva::query()
            ->with('inmueble', 'cliente')
            ->whereBetween('created_at', [$this->filtro->inicio(), $this->filtro->fin()])
            ->when(
                EstadoReserva::tryFrom((string) $this->filtro->estado),
                fn ($q, EstadoReserva $estado) => $q->where('estado', $estado)
            )
            ->recientes();
    }
}
