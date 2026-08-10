<?php

namespace App\Http\Requests;

use App\Reglas\PasswordSegura;
use Illuminate\Foundation\Http\FormRequest;

// HU-25.2: exige la contraseña actual antes de aceptar la nueva
class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'current_password'],
            'contrasena' => ['required', 'confirmed', 'different:contrasena_actual', PasswordSegura::reglas()],
        ];
    }

    public function messages(): array
    {
        return [
            'contrasena_actual.current_password' => 'La contraseña actual no es correcta.',
            'contrasena.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }
}
