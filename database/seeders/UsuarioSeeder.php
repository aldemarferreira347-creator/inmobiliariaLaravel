<?php

namespace Database\Seeders;

use App\Enumerados\RolUsuario;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
        // Contraseña inicial del administrador: obligatoria en producción.
        // Fuera de producción, si falta la variable, se conserva la contraseña de demostración.
        $contrasenaAdmin = env('ADMIN_PASSWORD_INICIAL');

        if (app()->isProduction() && blank($contrasenaAdmin)) {
            throw new RuntimeException('Defina ADMIN_PASSWORD_INICIAL para sembrar usuarios en producción.');
        }

        foreach (self::DEMO as $indice => $datos) {
            User::updateOrCreate(
                ['email' => $datos['email']],
                [
                    'nombre' => $datos['nombre'],
                    'contrasena' => Hash::make(
                        $datos['rol'] === RolUsuario::Administrador && filled($contrasenaAdmin)
                            ? $contrasenaAdmin
                            : UserFactory::PASSWORD
                    ),
                    'rol' => $datos['rol'],
                    'telefono' => '31000000'.$indice.'0',
                    'documento_tipo' => 'CC',
                    'documento_numero' => '10000000'.$indice,
                    'ciudad' => 'Neiva',
                ],
            );
        }

        User::factory()->count(8)->create();
    }
}
