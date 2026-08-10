<?php

namespace App\Http\Requests\Admin;

use App\Models\Cita;
use App\Models\ConfigFranjaCita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// RF-26.2: el administrador configura la franja horaria de un día de la semana
class ActualizarFranjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configurarFranjas', Cita::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'dia_semana' => ['required', Rule::in(ConfigFranjaCita::DIAS)],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }

    public function estaActiva(): bool
    {
        return $this->boolean('activo');
    }
}
