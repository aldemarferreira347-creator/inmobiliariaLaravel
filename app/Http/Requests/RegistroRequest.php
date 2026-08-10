<?php

namespace App\Http\Requests;

use App\Reglas\PasswordSegura;
use Illuminate\Foundation\Http\FormRequest;

// HU-03: datos del formulario público de registro
class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Letras, espacios y los signos . ' -
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s.\'-]{2,100}$/u'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:usuario,email'],
            'contrasena' => ['required', 'confirmed', PasswordSegura::reglas()],
            'documento_tipo' => ['required', 'string', 'max:20'],
            'documento_numero' => ['required', 'string', 'max:30', 'unique:usuario,documento_numero'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9\s\-+()]{7,20}$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras, espacios y los signos . \' -',
            'email.unique' => 'Este correo ya está registrado. Intenta iniciar sesión.',
            'documento_numero.unique' => 'Este número de documento ya está registrado en el sistema.',
            'telefono.regex' => 'El número de teléfono no tiene un formato válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
