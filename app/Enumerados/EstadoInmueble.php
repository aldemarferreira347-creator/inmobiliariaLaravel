<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estado del inmueble (HU-09).
 *
 * Se persiste en `inmueble.estado`, pero es un valor DERIVADO de las tablas
 * `reserva` y `contrato`: ver Inmueble::estadoCalculado(). El administrador
 * solo puede fijarlo manualmente cuando los datos relacionales lo respaldan.
 */
enum EstadoInmueble: string
{
    use ConValores;

    case Disponible = 'Disponible';
    case Reservado = 'Reservado';
    case Ocupado = 'Ocupado';

    public function etiqueta(): string
    {
        return $this->value;
    }

    // Sufijo de las clases CSS .estado-* y .badge-estado.* del prototipo
    public function claseCss(): string
    {
        return mb_strtolower($this->value);
    }

    // Solo un inmueble disponible admite acciones comerciales en el catálogo
    public function esReservable(): bool
    {
        return $this === self::Disponible;
    }
}
