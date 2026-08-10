<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

// Pasarela de pago a través de la que se tokenizó una tarjeta (HU-20)
enum PasarelaPago: string
{
    use ConValores;

    case Stripe = 'Stripe';

    public function etiqueta(): string
    {
        return $this->value;
    }
}
