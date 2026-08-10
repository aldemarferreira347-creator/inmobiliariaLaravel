<?php

namespace App\Http\Requests\Admin;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use App\Models\Inmueble;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del formulario de inmueble, tanto al crear como al editar (HU-04 / HU-08).
 *
 * Reglas propias del negocio que se verifican aquí:
 *   · la descripción exige un mínimo de 50 caracteres (HU-08.4);
 *   · la modalidad determina qué precios son obligatorios;
 *   · «Reservado» y «Ocupado» no se pueden fijar a mano sin una reserva o un
 *     contrato que los respalde.
 */
class InmuebleRequest extends FormRequest
{
    private const MAXIMO_IMAGENES = 10;

    private const MAXIMO_KB_IMAGEN = 5120;

    public function authorize(): bool
    {
        return $this->user()?->can($this->inmueble ? 'update' : 'create', $this->inmueble ?? Inmueble::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'min:'.Inmueble::DESCRIPCION_MINIMA],
            'tipo' => ['required', Rule::enum(TipoInmueble::class)],
            'modalidad' => ['required', Rule::enum(ModalidadInmueble::class)],
            'estado' => ['required', Rule::enum(EstadoInmueble::class)],
            'precio_venta' => [Rule::requiredIf($this->modalidadElegida()?->exigePrecioVenta() ?? false), 'nullable', 'numeric', 'min:1'],
            'precio_arrendamiento' => [Rule::requiredIf($this->modalidadElegida()?->exigePrecioArriendo() ?? false), 'nullable', 'numeric', 'min:1'],
            'ciudad' => ['required', 'string', 'max:100'],
            'barrio' => ['required', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'max:255'],
            'habitaciones' => ['required', 'integer', 'min:0', 'max:50'],
            'banos' => ['required', 'integer', 'min:0', 'max:50'],
            'area' => ['required', 'numeric', 'min:1'],
            'parqueadero' => ['required', 'boolean'],
            'imagenes' => ['nullable', 'array', 'max:'.self::MAXIMO_IMAGENES],
            'imagenes.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::MAXIMO_KB_IMAGEN],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.min' => 'La descripción debe tener al menos '.Inmueble::DESCRIPCION_MINIMA.' caracteres.',
            'precio_venta.required' => 'La modalidad elegida exige un precio de venta mayor a cero.',
            'precio_arrendamiento.required' => 'La modalidad elegida exige un precio de arriendo mayor a cero.',
            'imagenes.max' => 'Puedes subir un máximo de '.self::MAXIMO_IMAGENES.' imágenes a la vez.',
            'imagenes.*.max' => 'Cada imagen puede pesar como máximo 5 MB.',
            'imagenes.*.mimes' => 'Formato no admitido: usa JPG, PNG o WebP.',
        ];
    }

    // Comprueba que el estado pedido sea coherente con los datos relacionales del inmueble
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $inmueble = $this->inmueble;
                $estado = EstadoInmueble::tryFrom((string) $this->input('estado'));

                if ($inmueble === null || $estado === null || $inmueble->admiteEstado($estado)) {
                    return;
                }

                $validador->errors()->add('estado', $inmueble->motivoEstadoNoAdmitido($estado));
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parqueadero' => $this->boolean('parqueadero'),
            'precio_venta' => $this->limpiarPrecio($this->input('precio_venta')),
            'precio_arrendamiento' => $this->limpiarPrecio($this->input('precio_arrendamiento')),
        ]);
    }

    // Los precios que no aplican a la modalidad llegan vacíos y deben guardarse como NULL
    private function limpiarPrecio(mixed $valor): ?float
    {
        return is_numeric($valor) && (float) $valor > 0 ? (float) $valor : null;
    }

    private function modalidadElegida(): ?ModalidadInmueble
    {
        return ModalidadInmueble::tryFrom((string) $this->input('modalidad'));
    }
}
