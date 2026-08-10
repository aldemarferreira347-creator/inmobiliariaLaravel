<?php

namespace App\Servicios\Reportes;

use App\Enumerados\EstadoVenta;
use App\Enumerados\TipoReporte;
use App\Models\Venta;
use Illuminate\Support\Collection;

// HU-21.3: ventas del periodo con su inmueble, asesor, precio y estado
class ReporteVentas extends Reporte
{
    public function tipo(): TipoReporte
    {
        return TipoReporte::Ventas;
    }

    public function columnas(): array
    {
        return ['Inmueble', 'Cliente', 'Asesor', 'Precio', 'Fecha', 'Estado'];
    }

    public function columnasNumericas(): array
    {
        return [3];
    }

    public function filas(): Collection
    {
        return $this->consulta()
            ->get()
            ->map(fn (Venta $venta) => [
                $venta->inmueble->titulo,
                $venta->cliente->nombre,
                $venta->asesor_nombre,
                (float) $venta->precio_venta,
                $venta->fecha_venta->format('d/m/Y'),
                $venta->estado->etiqueta(),
            ]);
    }

    public function resumen(): array
    {
        $ventas = $this->consulta()->get();
        $cerradas = $ventas->where('estado', EstadoVenta::Cerrada);

        return [
            'Ventas' => $ventas->count(),
            'Cerradas' => $cerradas->count(),
            'En proceso' => $ventas->where('estado', EstadoVenta::EnProceso)->count(),
            'Importe cerrado' => $this->formatearMoneda($cerradas->sum('precio_venta')),
        ];
    }

    private function consulta()
    {
        return Venta::query()
            ->with('inmueble', 'cliente', 'asesor')
            ->whereBetween('fecha_venta', [$this->filtro->inicio(), $this->filtro->fin()])
            ->when(
                EstadoVenta::tryFrom((string) $this->filtro->estado),
                fn ($q, EstadoVenta $estado) => $q->where('estado', $estado)
            )
            ->latest('fecha_venta');
    }
}
