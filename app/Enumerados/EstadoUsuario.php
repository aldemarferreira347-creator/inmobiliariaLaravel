<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Estado de la cuenta (HU-16.2 / HU-26.3).
 * Un usuario inactivo no puede iniciar sesión y su sesión activa se corta
 * en la siguiente petición (middleware `activo`).
 */
enum EstadoUsuario: string
{
    use ConValores;

    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    public function opuesto(): self
    {
        return $this === self::Activo ? self::Inactivo : self::Activo;
    }
}
