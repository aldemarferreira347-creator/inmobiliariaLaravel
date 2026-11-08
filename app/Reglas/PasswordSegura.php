<?php

namespace App\Reglas;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Política de contraseñas del sistema, en un único lugar.
 *
 * Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un carácter
 * especial. La comparten el registro, el cambio autenticado, la recuperación
 * por enlace y el alta de usuarios del panel, para que no se desincronicen.
 * Ante cualquier incumplimiento se muestra el mismo mensaje de la HU-03.3.
 */
final class PasswordSegura implements ValidationRule
{
    public const MENSAJE = 'La contraseña debe tener mínimo 8 caracteres e incluir números y letras';

    public static function reglas(): self
    {
        return new self;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cumple = Validator::make(
            ['contrasena' => $value],
            ['contrasena' => [Password::min(8)->mixedCase()->numbers()->symbols()]],
        )->passes();

        if (! $cumple) {
            $fail(self::MENSAJE);
        }
    }
}
