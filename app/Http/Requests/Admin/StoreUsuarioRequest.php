<?php

namespace App\Http\Requests\Admin;

use App\Enumerados\RolUsuario;
use App\Models\User;
use App\Reglas\PasswordSegura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-16.1 / HU-26.2: alta de un usuario con el rol que decida el administrador
class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\s.\'-]{2,100}$/u'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:usuario,email'],
            'contrasena' => ['required', PasswordSegura::reglas()],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9\s\-+()]{7,20}$/'],
            'documento_tipo' => ['nullable', 'string', 'max:20'],
            'documento_numero' => ['nullable', 'string', 'max:30', 'unique:usuario,documento_numero'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre solo puede contener letras, espacios y los signos . \' -',
            'email.unique' => 'Ya existe un usuario registrado con ese correo.',
            'documento_numero.unique' => 'Ese número de documento ya está registrado.',
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
