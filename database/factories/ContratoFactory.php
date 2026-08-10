<?php

namespace Database\Factories;

use App\Enumerados\EstadoContrato;
use App\Models\Contrato;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory()->confirmada(),
            'numero_contrato' => sprintf('CON-%s-%05d', now()->year, fake()->unique()->numberBetween(1, 99_999)),
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now()->addYear(),
            'valor_mensual' => fake()->numberBetween(800, 4_000) * 1_000,
            'estado' => EstadoContrato::Vigente,
        ];
    }

    public function vencido(): static
    {
        return $this->state([
            'fecha_fin' => now()->subDay(),
            'estado' => EstadoContrato::Vigente,
        ]);
    }
}
