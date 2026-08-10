<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

// Medio por el que el cliente declara haber pagado la reserva (HU-21.1)
enum MetodoPago: string
{
    use ConValores;

    case Transferencia = 'transferencia';
    case Consignacion = 'consignacion';
    case Efectivo = 'efectivo';
    case TarjetaCredito = 'tarjeta_credito';
    case TarjetaDebito = 'tarjeta_debito';
    case Pse = 'pse';
    case Nequi = 'nequi';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia bancaria',
            self::Consignacion => 'Consignación',
            self::Efectivo => 'Efectivo',
            self::TarjetaCredito => 'Tarjeta de crédito',
            self::TarjetaDebito => 'Tarjeta débito',
            self::Pse => 'PSE',
            self::Nequi => 'Nequi',
        };
    }
}
