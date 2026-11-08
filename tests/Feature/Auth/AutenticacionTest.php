<?php

namespace Tests\Feature\Auth;

use App\Enumerados\EstadoUsuario;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

    public function test_hu_05_1_cada_rol_llega_a_su_pantalla_inicial(): void
    {
        foreach ([User::factory()->create(), User::factory()->asesor()->create(), User::factory()->administrador()->create()] as $usuario) {
            $this->post(route('login'), [
                'email' => $usuario->email,
                'password' => UserFactory::PASSWORD,
            ])->assertRedirect(route($usuario->rol->rutaInicio()));

            $this->post(route('logout'));
        }
    }

    public function test_hu_05_2_contrasena_incorrecta_de_correo_existente(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'Incorrecta1*'])
            ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos']);
    }

    public function test_hu_05_3_correo_inexistente_invita_a_registrarse(): void
    {
        $mensaje = 'No encontramos una cuenta con ese correo. ¿Deseas registrarte?';

        $this->post(route('login'), ['email' => 'nadie@ejemplo.test', 'password' => 'Incorrecta1*'])
            ->assertSessionHasErrors(['email' => $mensaje]);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), ['email' => 'nadie@ejemplo.test', 'password' => 'Incorrecta1*'])
            ->assertSee($mensaje)
            ->assertSee(route('register'), false);
    }

    public function test_hu_05_4_sesion_expirada_por_inactividad_muestra_el_aviso(): void
    {
        // El driver `array` de las pruebas no caduca por tiempo: se usa `file`, que sí lo hace
        config(['session.driver' => 'file', 'session.files' => storage_path('framework/testing-sesiones')]);
        File::ensureDirectoryExists(config('session.files'));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory(storage_path('framework/testing-sesiones')));
        $this->app['session']->forgetDrivers();

        $usuario = User::factory()->create();

        $login = $this->post(route('login'), ['email' => $usuario->email, 'password' => UserFactory::PASSWORD]);
        $cookies = collect($login->headers->getCookies())->mapWithKeys(fn ($c) => [$c->getName() => $c->getValue()])->all();

        $this->assertArrayHasKey('sesion_iniciada', $cookies);
        $this->call('GET', route('perfil.edit'), [], $cookies)->assertOk();

        // Entre peticiones reales no sobrevive nada en memoria: se descartan el guard ni el store de sesión
        $this->travel(16)->minutes();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();

        $this->call('GET', route('perfil.edit'), [], $cookies)
            ->assertRedirect(route('login'))
            ->assertSessionHas('mensaje', 'Su sesión ha expirado');
    }

    public function test_hu_05_4_sin_sesion_previa_no_se_muestra_el_aviso_de_expiracion(): void
    {
        $this->get(route('perfil.edit'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('mensaje');
    }

    public function test_hu_05_5_asesor_inactivo_con_credenciales_correctas(): void
    {
        $asesor = User::factory()->asesor()->inactivo()->create();

        $this->post(route('login'), ['email' => $asesor->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Su cuenta está inactiva. Contacte al administrador']);

        $this->assertGuest();
    }

    public function test_hu_24_1_correo_existente_genera_token_y_confirma(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('mensaje', 'Te enviamos por correo el enlace para restablecer tu contraseña.');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $usuario->email]);
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    public function test_hu_24_2_correo_inexistente_informa_que_no_existe_la_cuenta(): void
    {
        $this->post(route('password.email'), ['email' => 'nadie@ejemplo.test'])
            ->assertSessionHasErrors(['email' => 'No existe una cuenta asociada a este correo electrónico']);
    }

    public function test_hu_24_3_enlace_expirado_ofrece_solicitar_uno_nuevo(): void
    {
        $usuario = User::factory()->create();
        $token = Password::createToken($usuario);
        DB::table('password_reset_tokens')->where('email', $usuario->email)->update(['created_at' => now()->subMinutes(61)]);

        $datos = [
            'token' => $token,
            'email' => $usuario->email,
            'contrasena' => 'NuevaClave1*',
            'contrasena_confirmation' => 'NuevaClave1*',
        ];

        $this->post(route('password.store'), $datos)
            ->assertSessionHasErrors(['email' => 'El enlace ha expirado. Solicite uno nuevo']);

        $this->followingRedirects()
            ->from(route('password.reset', ['token' => $token]))
            ->post(route('password.store'), $datos)
            ->assertSee('El enlace ha expirado. Solicite uno nuevo')
            ->assertSee(route('password.request'), false);

        $this->assertFalse(Hash::check('NuevaClave1*', $usuario->refresh()->contrasena));
    }

    public function test_hu_24_4_token_valido_restablece_la_contrasena_con_bcrypt(): void
    {
        $usuario = User::factory()->create();
        $token = Password::createToken($usuario);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $usuario->email,
            'contrasena' => 'NuevaClave1*',
            'contrasena_confirmation' => 'NuevaClave1*',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('mensaje', 'Contraseña restablecida exitosamente');

        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave1*', $usuario->contrasena));
        $this->assertSame('bcrypt', Hash::info($usuario->contrasena)['algoName']);
    }
}
