<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

// Tipos de inmueble ofrecidos en el catálogo y en el formulario del panel (HU-02.1)
enum TipoInmueble: string
{
    use ConValores;

    case Apartamento = 'apartamento';
    case Casa = 'casa';
    case Lote = 'lote';
    case Oficina = 'oficina';
    case Finca = 'finca';
    case Local = 'local';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Apartamento => 'Apartamento',
            self::Casa => 'Casa',
            self::Lote => 'Lote',
            self::Oficina => 'Oficina',
            self::Finca => 'Finca',
            self::Local => 'Local',
        };
    }
}
