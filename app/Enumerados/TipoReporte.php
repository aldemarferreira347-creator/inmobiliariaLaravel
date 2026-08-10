<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Tipos de reporte del panel (HU-06 y HU-21).
 * El prototipo volcaba todo en una sola pantalla; aquí cada tipo es una vista
 * propia con sus filtros y sus exportaciones, como pide la documentación.
 */
enum TipoReporte: string
{
    use ConValores;

    case Reservaciones = 'reservaciones';
    case Pagos = 'pagos';
    case Contratos = 'contratos';
    case Ventas = 'ventas';
    case Integral = 'integral';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Reservaciones => 'Reservaciones',
            self::Pagos => 'Pagos',
            self::Contratos => 'Contratos vigentes',
            self::Ventas => 'Ventas',
            self::Integral => 'Reportes del sistema',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Reservaciones => 'Reservas realizadas con su inmueble, cliente, monto y estado.',
            self::Pagos => 'Pagos registrados con su monto, método y estado.',
            self::Contratos => 'Contratos vigentes con su inmueble, cliente, valor mensual y fecha de fin.',
            self::Ventas => 'Ventas con su inmueble, asesor, precio y estado.',
            self::Integral => 'Análisis integral de inmuebles, reservas, clientes e ingresos.',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Reservaciones => 'clipboard-list',
            self::Pagos => 'credit-card',
            self::Contratos => 'file-text',
            self::Ventas => 'dollar-sign',
            self::Integral => 'chart-pie',
        };
    }
}
