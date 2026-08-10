<?php

namespace App\Enumerados\Concerns;

trait ConValores
{
    // Devuelve los valores crudos del enum, para migraciones y reglas de validación
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Devuelve un mapa valor => etiqueta legible, para poblar los <select> de las vistas
    public static function opciones(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $acc, self $caso) => $acc + [$caso->value => $caso->etiqueta()],
            []
        );
    }
}
