<?php

namespace App\Http\Requests\Admin;

use App\Enumerados\RolUsuario;
use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-10.1 / HU-10.3: el administrador asigna o reasigna un asesor a una cita
class AsignarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asignar', Cita::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'asesor_id' => [
                'required',
                Rule::exists('usuario', 'id')->where('rol', RolUsuario::Asesor->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'asesor_id.exists' => 'Selecciona un asesor válido.',
        ];
    }
}
