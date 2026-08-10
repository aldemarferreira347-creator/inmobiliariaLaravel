<?php

namespace App\Http\Requests;

use App\Enumerados\ModalidadInmueble;
use App\Models\Reserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-07.1: datos que el cliente envía al reservar un inmueble
class SolicitarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reserva::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inmueble_id' => ['required', 'exists:inmueble,id'],
            // Solo se pide cuando el inmueble admite venta y arriendo a la vez
            'modalidad' => ['nullable', Rule::enum(ModalidadInmueble::class)],
            'notas_cliente' => ['nullable', 'string', 'max:1000'],
            'acepta_terminos' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acepta_terminos.accepted' => 'Debes aceptar las condiciones de la reserva para continuar.',
        ];
    }

    public function modalidad(): ?ModalidadInmueble
    {
        return ModalidadInmueble::tryFrom((string) $this->input('modalidad'));
    }
}
