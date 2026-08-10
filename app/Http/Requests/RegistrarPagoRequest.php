<?php

namespace App\Http\Requests;

use App\Enumerados\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HU-23: el cliente declara el pago de su reserva.
 * El monto no se pide: se toma de la reserva para que no pueda alterarse.
 */
class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registrarPago', $this->route('reserva')) ?? false;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'referencia' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function metodo(): MetodoPago
    {
        return MetodoPago::from($this->string('metodo_pago')->toString());
    }
}
