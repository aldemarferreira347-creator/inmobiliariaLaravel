<?php

namespace Database\Seeders;

use App\Enumerados\RolUsuario;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

// Usuarios de demostración, uno por rol, más clientes de relleno
class UsuarioSeeder extends Seeder
{
    private const DEMO = [
        ['nombre' => 'Ana García', 'email' => 'admin@inmobiliaria.test', 'rol' => RolUsuario::Administrador],
        ['nombre' => 'Carlos Rojas', 'email' => 'asesor@inmobiliaria.test', 'rol' => RolUsuario::Asesor],
        ['nombre' => 'Laura Méndez', 'email' => 'cliente@inmobiliaria.test', 'rol' => RolUsuario::Cliente],
    ];

    public function run(): void
    {
        // En producción cada usuario sembrado recibe una contraseña propia y aleatoria.
        // Fuera de producción, si falta la variable, se conserva la de demostración.
        $variables = [
            RolUsuario::Administrador->value => 'ADMIN_PASSWORD_INICIAL',
            RolUsuario::Asesor->value => 'ASESOR_PASSWORD_INICIAL',
            RolUsuario::Cliente->value => 'CLIENTE_PASSWORD_INICIAL',
        ];
        $contrasenas = [];
        foreach ($variables as $rol => $variable) {
            $valor = env($variable);
            if (app()->isProduction() && blank($valor)) {
                throw new RuntimeException("Defina {$variable} para sembrar usuarios en producción.");
            }
            $contrasenas[$rol] = filled($valor) ? $valor : UserFactory::PASSWORD;
        }

        foreach (self::DEMO as $indice => $datos) {
            User::updateOrCreate(
                ['email' => $datos['email']],
                [
                    'nombre' => $datos['nombre'],
                    'contrasena' => Hash::make($contrasenas[$datos['rol']->value]),
                    'rol' => $datos['rol'],
                    'telefono' => '31000000'.$indice.'0',
                    'documento_tipo' => 'CC',
                    'documento_numero' => '10000000'.$indice,
                    'ciudad' => 'Neiva',
                ],
            );
        }

        // Clientes de relleno: en producción nadie inicia sesión con ellos
        $relleno = User::factory()->count(8);
        if (app()->isProduction()) {
            $relleno = $relleno->state(fn () => ['contrasena' => Hash::make(Str::random(40))]);
        }
        $relleno->create();
    }
}
