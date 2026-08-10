<?php

namespace App\Servicios\Reportes;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\EstadoVenta;
use App\Enumerados\TipoReporte;

// Resuelve el reporte que corresponde a cada tipo pedido desde la URL
class FabricaReportes
{
    public function crear(TipoReporte $tipo, FiltroReporte $filtro): Reporte
    {
        return match ($tipo) {
            TipoReporte::Reservaciones => new ReporteReservaciones($filtro),
            TipoReporte::Pagos => new ReportePagos($filtro),
            TipoReporte::Contratos => new ReporteContratos($filtro),
            TipoReporte::Ventas => new ReporteVentas($filtro),
            TipoReporte::Integral => new ReporteIntegral($filtro),
        };
    }

    /** Estados que ofrece el filtro de cada tipo de reporte */
    public function estadosDe(TipoReporte $tipo): array
    {
        return match ($tipo) {
            TipoReporte::Reservaciones => EstadoReserva::opciones(),
            TipoReporte::Pagos => EstadoPago::opciones(),
            TipoReporte::Contratos => EstadoContrato::opciones(),
            TipoReporte::Ventas => EstadoVenta::opciones(),
            TipoReporte::Integral => [],
        };
    }
}
