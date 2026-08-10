<?php

namespace Tests\Feature\Auth;

use App\Enumerados\RolUsuario;
use App\Models\User;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Laura Méndez',
            'email' => 'laura@ejemplo.test',
            'contrasena' => 'Password1*',
            'contrasena_confirmation' => 'Password1*',
            'documento_tipo' => 'CC',
            'documento_numero' => '1075123456',
        ], $cambios);
    }

    public function test_la_pantalla_de_registro_se_muestra(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_el_registro_crea_siempre_una_cuenta_de_cliente(): void
    {
        $this->post(route('register'), $this->datosValidos())->assertRedirect(route('inicio'));

        $usuario = User::where('email', 'laura@ejemplo.test')->firstOrFail();

        $this->assertSame(RolUsuario::Cliente, $usuario->rol);
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_se_rechaza_una_contrasena_debil(): void
    {
        $this->post(route('register'), $this->datosValidos([
            'contrasena' => 'abcdefgh',
            'contrasena_confirmation' => 'abcdefgh',
        ]))->assertSessionHasErrors('contrasena');

        $this->assertGuest();
    }

    public function test_no_se_admite_un_correo_ya_registrado(): void
    {
        User::factory()->create(['email' => 'laura@ejemplo.test']);

        $this->post(route('register'), $this->datosValidos())->assertSessionHasErrors('email');
    }

    public function test_no_se_admite_un_documento_ya_registrado(): void
    {
        User::factory()->create(['documento_numero' => '1075123456']);

        $this->post(route('register'), $this->datosValidos())->assertSessionHasErrors('documento_numero');
    }
}
