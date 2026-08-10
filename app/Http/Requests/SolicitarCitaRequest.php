<?php

namespace App\Http\Requests;

use App\Models\Cita;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

// HU-27.1: el cliente elige fecha/hora para visitar un inmueble
class SolicitarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cita::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inmueble_id' => ['required', 'exists:inmueble,id'],
            'fecha_cita' => ['required', 'date_format:Y-m-d'],
            'hora_cita' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $fechaHora = $this->fechaHoraCruda();
            if ($fechaHora === null) {
                return;
            }

            if ($fechaHora->lessThanOrEqualTo(now())) {
                $validator->errors()->add('hora_cita', 'La fecha de la visita debe ser futura.');

                return;
            }

            if ($fechaHora->greaterThan(now()->addDays(30))) {
                $validator->errors()->add('fecha_cita', 'No puedes agendar una visita con más de 30 días de anticipación.');
            }
        });
    }

    public function fechaHora(): Carbon
    {
        return $this->fechaHoraCruda();
    }

    private function fechaHoraCruda(): ?Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$this->input('fecha_cita')} {$this->input('hora_cita')}") ?: null;
    }
}
