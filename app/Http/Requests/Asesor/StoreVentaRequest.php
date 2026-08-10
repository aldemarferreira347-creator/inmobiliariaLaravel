<?php

namespace App\Http\Requests\Asesor;

use App\Enumerados\RolUsuario;
use App\Models\Venta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-14.1: el asesor registra una venta sobre un inmueble disponible
class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venta::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inmueble_id' => ['required', 'exists:inmueble,id'],
            'usuario_id' => [
                'required',
                Rule::exists('usuario', 'id')->where('rol', RolUsuario::Cliente->value),
            ],
            'precio_venta' => ['required', 'numeric', 'min:1'],
            'fecha_venta' => ['required', 'date', 'before_or_equal:today'],
            'notaria' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.exists' => 'El comprador debe ser un cliente registrado.',
            'fecha_venta.before_or_equal' => 'La fecha de la venta no puede ser futura.',
        ];
    }
}
