<?php

namespace App\Http\Requests\Admin;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;

// HU-17.1: alta de un contrato a partir de una reserva confirmada
class StoreContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contrato::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'reserva_id' => ['required', 'exists:reserva,id'],
            'fecha_inicio' => ['required', 'date'],
            // Dejarla vacía significa contrato indefinido
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'valor_mensual' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
        ];
    }
}
