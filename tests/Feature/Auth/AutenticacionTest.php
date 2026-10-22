<?php

namespace Tests\Feature\Auth;

use App\Enumerados\EstadoUsuario;
use App\Models\User;
use Database\Factories\UserFactory;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_un_cliente_inicia_sesion_y_llega_al_inicio(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => UserFactory::PASSWORD,
        ])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_administrador_inicia_sesion_y_llega_al_panel(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => UserFactory::PASSWORD,
        ])->assertRedirect(route('admin.inmuebles.index'));
    }

    public function test_no_se_inicia_sesion_con_una_contrasena_incorrecta(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => 'Incorrecta1*',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => UserFactory::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_desactivar_una_cuenta_corta_su_sesion_en_curso(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('perfil.edit'))->assertOk();

        $usuario->update(['estado' => EstadoUsuario::Inactivo]);

        $this->get(route('perfil.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_usuario_puede_cerrar_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('logout'))->assertRedirect(route('inicio'));

        $this->assertGuest();
    }

    public function test_el_login_ignora_mayusculas_y_espacios_del_correo(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), [
            'email' => '  '.mb_strtoupper($usuario->email).' ',
            'password' => UserFactory::PASSWORD,
        ])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_recuperar_la_contrasena_no_revela_si_el_correo_existe(): void
    {
        $mensaje = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.';

        $this->post(route('password.email'), ['email' => User::factory()->create()->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('mensaje', $mensaje);

        $this->post(route('password.email'), ['email' => 'nadie@ejemplo.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('mensaje', $mensaje);
    }

    public function test_las_respuestas_incluyen_cabeceras_de_seguridad(): void
    {
        $this->get(route('inicio'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_el_registro_esta_limitado_por_frecuencia(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('register'), []);
        }

        $this->post(route('register'), [])->assertStatus(429);
    }

    public function test_cambiar_la_contrasena_invalida_el_recuerdame(): void
    {
        $usuario = User::factory()->create(['remember_token' => 'token-viejo']);

        $this->actingAs($usuario)->put(route('password.update'), [
            'contrasena_actual' => UserFactory::PASSWORD,
            'contrasena' => 'NuevaClave2*Segura',
            'contrasena_confirmation' => 'NuevaClave2*Segura',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('token-viejo', $usuario->refresh()->remember_token);
    }
}
