<?php

namespace Database\Factories;

use App\Enumerados\EstadoPago;
use App\Enumerados\MetodoPago;
use App\Models\Pago;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reserva_id' => Reserva::factory(),
            'metodo_pago' => fake()->randomElement(MetodoPago::cases()),
            'moneda' => 'COP',
            'monto' => fake()->numberBetween(800, 4_000) * 1_000,
            'referencia' => fake()->numerify('REF-########'),
            'estado' => EstadoPago::Pendiente,
        ];
    }

    public function pagado(): static
    {
        return $this->state([
            'estado' => EstadoPago::Pagado,
            'revisado_en' => now(),
        ]);
    }

    public function rechazado(string $motivo = 'La referencia no coincide con ningún movimiento.'): static
    {
        return $this->state([
            'estado' => EstadoPago::Rechazado,
            'motivo_rechazo' => $motivo,
            'revisado_en' => now(),
        ]);
    }
}
