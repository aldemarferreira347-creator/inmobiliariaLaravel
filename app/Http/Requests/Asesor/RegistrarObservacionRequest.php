<?php

namespace App\Http\Requests\Asesor;

use Illuminate\Foundation\Http\FormRequest;

// HU-12.1 / HU-12.2: el asesor registra las observaciones de la visita realizada
class RegistrarObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registrarObservacion', $this->route('cita')) ?? false;
    }

    public function rules(): array
    {
        return [
            'observaciones' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'observaciones.required' => 'Debe ingresar observaciones de la visita antes de marcar como realizada.',
        ];
    }
}
