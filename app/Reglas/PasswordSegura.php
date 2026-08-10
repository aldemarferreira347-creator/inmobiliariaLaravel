<?php

namespace App\Reglas;

use Illuminate\Validation\Rules\Password;

/**
 * Política de contraseñas del sistema, en un único lugar.
 *
 * Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un carácter
 * especial. La comparten el registro, el cambio autenticado y la recuperación
 * por enlace, para que no se desincronicen entre sí.
 */
final class PasswordSegura
{
    public static function reglas(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }
}
