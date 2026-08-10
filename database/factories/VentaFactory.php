<?php

namespace Database\Factories;

use App\Enumerados\EstadoVenta;
use App\Models\Inmueble;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inmueble_id' => Inmueble::factory(),
            'usuario_id' => User::factory(),
            'asesor_id' => User::factory()->asesor(),
            'precio_venta' => fake()->numberBetween(120, 850) * 1_000_000,
            'fecha_venta' => fake()->dateTimeBetween('-6 months', 'now'),
            'notaria' => 'Notaría '.fake()->numberBetween(1, 5).' de Neiva',
            'estado' => EstadoVenta::EnProceso,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(['estado' => EstadoVenta::Cerrada]);
    }
}
