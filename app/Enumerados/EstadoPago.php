<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estados de un pago (HU-23).
 *
 * Mientras no haya pasarela, quien mueve el pago a PAGADO o RECHAZADO es el
 * administrador desde el panel. Cuando se integre la pasarela, el driver
 * correspondiente aplicará las mismas transiciones sin tocar este enum.
 */
enum EstadoPago: string
{
    use ConValores;

    case Pendiente = 'PENDIENTE';
    case Procesando = 'PROCESANDO';
    case Pagado = 'PAGADO';
    case Rechazado = 'RECHAZADO';
    case Reembolsado = 'REEMBOLSADO';
    case Expirado = 'EXPIRADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Procesando => 'En revisión',
            self::Pagado => 'Pagado',
            self::Rechazado => 'Rechazado',
            self::Reembolsado => 'Reembolsado',
            self::Expirado => 'Expirado',
        };
    }

    public function claseBadge(): string
    {
        return match ($this) {
            self::Pendiente, self::Procesando => 'badge-pendiente-pago',
            self::Pagado => 'badge-confirmada',
            self::Rechazado => 'badge-rechazada',
            self::Reembolsado => 'badge-cancelada',
            self::Expirado => 'badge-expirada',
        };
    }

    // Un pago en revisión es el único que el administrador puede aprobar o rechazar
    public function admiteRevision(): bool
    {
        return in_array($this, [self::Pendiente, self::Procesando], true);
    }
}
