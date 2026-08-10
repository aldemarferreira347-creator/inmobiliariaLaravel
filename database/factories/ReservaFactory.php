<?php

namespace Database\Factories;

use App\Enumerados\EstadoReserva;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo_reserva' => Reserva::generarCodigo(),
            'inmueble_id' => Inmueble::factory(),
            'usuario_id' => User::factory(),
            'monto_reserva' => fake()->numberBetween(800, 4_000) * 1_000,
            'estado' => EstadoReserva::PendientePago,
            'expira_en' => now()->addHours(Reserva::HORAS_PARA_PAGAR),
            'notas_cliente' => fake()->optional()->sentence(),
        ];
    }

    public function confirmada(): static
    {
        return $this->state(['estado' => EstadoReserva::Confirmada]);
    }

    public function vencida(): static
    {
        return $this->state([
            'estado' => EstadoReserva::PendientePago,
            'expira_en' => now()->subHour(),
        ]);
    }
}
