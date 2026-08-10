<?php

namespace App\Http\Requests\Admin;

use App\Models\Reserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// El administrador aprueba o rechaza un pago; al rechazar debe indicar el motivo
class RevisarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('revisarPago', Reserva::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['aprobar', 'rechazar'])],
            'motivo_rechazo' => ['required_if:decision,rechazar', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_rechazo.required_if' => 'Indica el motivo por el que se rechaza el pago.',
        ];
    }

    public function apruebaElPago(): bool
    {
        return $this->input('decision') === 'aprobar';
    }
}
