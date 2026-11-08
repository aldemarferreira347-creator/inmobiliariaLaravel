<?php

namespace Tests\Feature\Auth;

use App\Enumerados\RolUsuario;
use App\Mail\Aviso;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    public function test_hu_03_4_contrasena_se_guarda_con_bcrypt(): void
    {
        $this->post(route('register'), $this->datosValidos())->assertRedirect(route('inicio'));

        $usuario = User::where('email', 'laura@ejemplo.test')->firstOrFail();

        $this->assertSame('bcrypt', Hash::info($usuario->contrasena)['algoName']);
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

    public function test_hu_03_1_registro_exitoso_muestra_el_mensaje(): void
    {
        $this->post(route('register'), $this->datosValidos(['telefono' => '3001234567']))
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('mensaje', 'Registro exitoso. Bienvenido');

        $this->assertDatabaseHas('usuario', ['email' => 'laura@ejemplo.test', 'telefono' => '3001234567']);
    }

    public function test_hu_03_2_correo_existente_pide_iniciar_sesion(): void
    {
        User::factory()->create(['email' => 'laura@ejemplo.test']);

        $this->post(route('register'), $this->datosValidos())
            ->assertSessionHasErrors(['email' => 'El correo ingresado ya está registrado. Por favor inicie sesión.']);
    }

    public function test_hu_03_3_contrasena_debil_muestra_el_mensaje_de_la_politica(): void
    {
        foreach (['abc', 'abcdefgh'] as $debil) {
            $this->post(route('register'), $this->datosValidos([
                'contrasena' => $debil,
                'contrasena_confirmation' => $debil,
            ]))->assertSessionHasErrors([
                'contrasena' => 'La contraseña debe tener mínimo 8 caracteres e incluir números y letras',
            ]);
        }
    }

    public function test_hu_03_3_regla_literal_letras_y_numeros_minimo_8(): void
    {
        $this->post(route('register'), $this->datosValidos([
            'contrasena' => 'abcd1234',
            'contrasena_confirmation' => 'abcd1234',
        ]))->assertSessionHasNoErrors();
        $this->post(route('logout'));

        foreach (['abcdefgh', '12345678', 'abc1'] as $invalida) {
            $this->post(route('register'), $this->datosValidos([
                'email' => $invalida.'@ejemplo.test',
                'documento_numero' => '9'.crc32($invalida),
                'contrasena' => $invalida,
                'contrasena_confirmation' => $invalida,
            ]))->assertSessionHasErrors([
                'contrasena' => 'La contraseña debe tener mínimo 8 caracteres e incluir números y letras',
            ]);
        }
    }

    public function test_hu_03_4_se_envia_correo_de_bienvenida_al_registrarse(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->datosValidos())->assertRedirect(route('inicio'));

        Mail::assertQueued(Aviso::class, fn (Aviso $correo) => $correo->hasTo('laura@ejemplo.test'));
    }
}
