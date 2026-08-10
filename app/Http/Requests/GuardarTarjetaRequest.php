<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-20.1: confirma el guardado de una tarjeta ya tokenizada por Stripe.js/Elements
class GuardarTarjetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'string', 'starts_with:pm_'],
            'customer_id' => ['required', 'string', 'starts_with:cus_'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_id.starts_with' => 'Identificador de tarjeta inválido.',
            'customer_id.starts_with' => 'Identificador de cliente inválido.',
        ];
    }
}
