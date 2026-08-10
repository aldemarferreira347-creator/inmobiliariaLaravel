<?php

namespace Database\Factories;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use App\Models\Inmueble;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inmueble>
 */
class InmuebleFactory extends Factory
{
    public function definition(): array
    {
        $modalidad = fake()->randomElement(ModalidadInmueble::cases());
        $tipo = fake()->randomElement(TipoInmueble::cases());

        return [
            'codigo' => Inmueble::generarCodigo(),
            'titulo' => $tipo->etiqueta().' en '.fake()->randomElement(['Altico', 'Candado', 'Ventilador', 'Quirinal', 'Buganviles']),
            'descripcion' => fake()->paragraph(6),
            'tipo' => $tipo,
            'modalidad' => $modalidad,
            'estado' => EstadoInmueble::Disponible,
            'precio_venta' => $modalidad->exigePrecioVenta() ? fake()->numberBetween(120, 850) * 1_000_000 : null,
            'precio_arrendamiento' => $modalidad->exigePrecioArriendo() ? fake()->numberBetween(700, 4_500) * 1_000 : null,
            'ciudad' => 'Neiva',
            'barrio' => fake()->randomElement(['El Altico', 'Candado', 'Quirinal', 'Buganviles', 'Los Cámbulos']),
            'direccion' => fake()->streetAddress(),
            'habitaciones' => fake()->numberBetween(1, 5),
            'banos' => fake()->numberBetween(1, 4),
            'area' => fake()->numberBetween(45, 320),
            'parqueadero' => fake()->boolean(70),
        ];
    }

    public function deVenta(): static
    {
        return $this->state([
            'modalidad' => ModalidadInmueble::Venta,
            'precio_venta' => fake()->numberBetween(120, 850) * 1_000_000,
            'precio_arrendamiento' => null,
        ]);
    }

    public function deArriendo(): static
    {
        return $this->state([
            'modalidad' => ModalidadInmueble::Arriendo,
            'precio_venta' => null,
            'precio_arrendamiento' => fake()->numberBetween(700, 4_500) * 1_000,
        ]);
    }
}
