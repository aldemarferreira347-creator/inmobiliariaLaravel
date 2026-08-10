<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HU-25.1: datos editables del perfil.
 *
 * El correo y el documento no aparecen aquí de forma deliberada: identifican la
 * cuenta y son inmutables desde el perfil, igual que en el prototipo.
 */
class PerfilUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s.\'-]{2,100}$/u'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9\s\-+()]{7,20}$/'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras, espacios y los signos . \' -',
            'telefono.regex' => 'El número de teléfono no tiene un formato válido.',
        ];
    }
}
