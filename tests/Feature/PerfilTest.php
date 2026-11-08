<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    public function test_el_perfil_se_muestra_al_usuario_autenticado(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('perfil.edit'))
            ->assertOk();
    }

    public function test_se_actualizan_los_datos_editables(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('perfil.update'), [
                'nombre' => 'Nombre Actualizado',
                'telefono' => '3001234567',
                'ciudad' => 'Neiva',
                'direccion' => 'Cra 5 # 10-20',
            ])
            ->assertRedirect(route('perfil.edit'));

        $this->assertSame('Nombre Actualizado', $usuario->refresh()->nombre);
    }

    public function test_el_correo_no_se_modifica_desde_el_perfil(): void
    {
        $usuario = User::factory()->create(['email' => 'original@ejemplo.test']);

        $this->actingAs($usuario)->patch(route('perfil.update'), [
            'nombre' => $usuario->nombre,
            'email' => 'otro@ejemplo.test',
        ]);

        $this->assertSame('original@ejemplo.test', $usuario->refresh()->email);
    }

    public function test_se_rechaza_una_foto_demasiado_grande(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('perfil.foto.store'), [
                'foto' => UploadedFile::fake()->image('avatar.jpg')->size(4096),
            ])
            ->assertSessionHasErrors('foto');
    }

    public function test_se_guarda_y_se_elimina_la_foto_de_perfil(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('perfil.foto.store'), [
            'foto' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ])->assertRedirect(route('perfil.edit'));

        $this->assertTrue($usuario->refresh()->tieneFotoPropia());

        $this->delete(route('perfil.foto.destroy'))->assertRedirect(route('perfil.edit'));

        $this->assertFalse($usuario->refresh()->tieneFotoPropia());
    }

    public function test_el_cambio_de_contrasena_exige_la_actual(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->put(route('password.update'), [
                'contrasena_actual' => 'Incorrecta1*',
                'contrasena' => 'NuevaClave1*',
                'contrasena_confirmation' => 'NuevaClave1*',
            ])
            ->assertSessionHasErrors('contrasena_actual');
    }

    public function test_se_rechaza_una_contrasena_nueva_debil(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->put(route('password.update'), [
                'contrasena_actual' => UserFactory::PASSWORD,
                'contrasena' => 'sencilla',
                'contrasena_confirmation' => 'sencilla',
            ])
            ->assertSessionHasErrors('contrasena');
    }

    public function test_la_contrasena_se_actualiza_correctamente(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->put(route('password.update'), [
                'contrasena_actual' => UserFactory::PASSWORD,
                'contrasena' => 'NuevaClave1*',
                'contrasena_confirmation' => 'NuevaClave1*',
            ])
            ->assertRedirect(route('perfil.edit'));

        $this->assertTrue(Hash::check('NuevaClave1*', $usuario->refresh()->contrasena));
    }

    public function test_hu_25_1_editar_nombre_y_telefono_muestra_el_mensaje(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('perfil.update'), ['nombre' => 'Nuevo Nombre', 'telefono' => '3109876543'])
            ->assertRedirect(route('perfil.edit'))
            ->assertSessionHas('mensaje', 'Perfil actualizado exitosamente');

        $this->assertSame('3109876543', $usuario->refresh()->telefono);
    }

    public function test_hu_25_2_contrasena_actual_incorrecta_y_correcta(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->put(route('password.update'), [
            'contrasena_actual' => 'Incorrecta1*',
            'contrasena' => 'NuevaClave1*',
            'contrasena_confirmation' => 'NuevaClave1*',
        ])->assertSessionHasErrors('contrasena_actual');

        $this->put(route('password.update'), [
            'contrasena_actual' => UserFactory::PASSWORD,
            'contrasena' => 'NuevaClave1*',
            'contrasena_confirmation' => 'NuevaClave1*',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave1*', $usuario->refresh()->contrasena));
    }

    public function test_hu_25_3_campos_invalidos_dan_un_error_por_campo(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('perfil.update'), ['nombre' => '123', 'telefono' => 'abc'])
            ->assertSessionHasErrors(['nombre', 'telefono']);
    }

    public function test_hu_25_4_foto_jpg_de_2_mb_se_guarda_y_png_grande_o_webp_se_rechazan(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('perfil.foto.store'), [
            'foto' => UploadedFile::fake()->image('avatar.jpg')->size(2048),
        ])->assertSessionHasNoErrors();

        $usuario->refresh();
        $this->assertTrue($usuario->tieneFotoPropia());
        $this->assertStringStartsWith('storage/avatares/', $usuario->foto_url);

        $this->post(route('perfil.foto.store'), [
            'foto' => UploadedFile::fake()->image('grande.png')->size(2150),
        ])->assertSessionHasErrors('foto');

        $this->post(route('perfil.foto.store'), [
            'foto' => UploadedFile::fake()->image('foto.webp'),
        ])->assertSessionHasErrors('foto');
    }
}
