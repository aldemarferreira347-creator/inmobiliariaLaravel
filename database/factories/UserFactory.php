<?php

namespace Database\Factories;

use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    // Contraseña compartida por todos los usuarios generados; cumple la política de PasswordSegura
    public const PASSWORD = 'Password1*';

    protected static ?string $hash = null;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'contrasena' => static::$hash ??= Hash::make(self::PASSWORD),
            'telefono' => fake()->numerify('3#########'),
            'rol' => RolUsuario::Cliente,
            'documento_tipo' => 'CC',
            'documento_numero' => fake()->unique()->numerify('##########'),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'ciudad' => fake()->randomElement(['Neiva', 'Bogotá', 'Ibagué', 'Pitalito']),
            'direccion' => fake()->streetAddress(),
            'estado' => EstadoUsuario::Activo,
        ];
    }

    public function administrador(): static
    {
        return $this->state(['rol' => RolUsuario::Administrador]);
    }

    public function asesor(): static
    {
        return $this->state(['rol' => RolUsuario::Asesor]);
    }

    public function inactivo(): static
    {
        return $this->state([
            'estado' => EstadoUsuario::Inactivo,
            'desactivado_en' => now(),
        ]);
    }
}
