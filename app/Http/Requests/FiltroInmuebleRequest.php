<?php

namespace App\Http\Requests;

use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del catálogo público (HU-02).
 * Todos son opcionales: combinarlos acota el resultado y omitirlos devuelve el
 * listado completo.
 */
class FiltroInmuebleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', Rule::enum(TipoInmueble::class)],
            'modalidad' => ['nullable', Rule::enum(ModalidadInmueble::class)],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0'],
            'habitaciones' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    // Filtros efectivamente aplicados, para conservarlos en el formulario y en la paginación
    public function filtros(): array
    {
        return array_filter($this->validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
