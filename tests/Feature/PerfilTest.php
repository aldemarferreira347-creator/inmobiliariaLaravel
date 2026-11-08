<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
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

    private function arriendoDelCliente(bool $conContrato = true): Reserva
    {
        $reserva = Reserva::factory()->confirmada()->create();

        if ($conContrato) {
            Contrato::factory()->create(['reserva_id' => $reserva->id]);
        }

        return $reserva->refresh();
    }

    public function test_hu_19_1_mis_arriendos_muestra_inmueble_contrato_valor_ultimo_pago_y_estado(): void
    {
        $reserva = $this->arriendoDelCliente();
        Pago::factory()->pagado()->create(['reserva_id' => $reserva->id, 'monto' => 1_234_000]);
        Pago::factory()->rechazado()->create(['reserva_id' => $reserva->id, 'monto' => 999_000]);

        $this->actingAs($reserva->cliente)
            ->get(route('perfil.edit'))
            ->assertOk()
            ->assertSeeInOrder(['Mis arriendos', 'Inmueble', 'Contrato', 'Valor mensual', 'Último pago', 'Estado'])
            ->assertSee($reserva->inmueble->titulo)
            ->assertSee($reserva->contrato->numero_contrato)
            ->assertSee($reserva->contrato->valor_formateado)
            ->assertSee('$1.234.000')
            ->assertSee($reserva->contrato->estado->etiqueta());
    }

    public function test_hu_19_2_mis_compras_muestra_inmueble_asesor_precio_fecha_y_estado(): void
    {
        $venta = Venta::factory()->create();

        $this->actingAs($venta->cliente)
            ->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee($venta->inmueble->titulo)
            ->assertSee($venta->asesor->nombre)
            ->assertSee($venta->precio_formateado)
            ->assertSee($venta->fecha_venta->format('d/m/Y'))
            ->assertSee($venta->estado->etiqueta());
    }

    public function test_hu_19_3_descargar_contrato_devuelve_el_pdf(): void
    {
        Storage::fake('local');
        $reserva = $this->arriendoDelCliente();
        $contrato = $reserva->contrato;
        Storage::disk('local')->put('contratos/firmado.pdf', '%PDF-1.4
%fin
');
        $contrato->update(['archivo_ruta' => 'contratos/firmado.pdf']);

        $this->actingAs($reserva->cliente)
            ->get(route('perfil.edit'))
            ->assertSee('Descargar contrato');

        $this->actingAs($reserva->cliente)
            ->get(route('contratos.descargar', $contrato))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_hu_19_4_reserva_confirmada_sin_contrato_muestra_contrato_pendiente(): void
    {
        $reserva = $this->arriendoDelCliente(conContrato: false);

        $this->actingAs($reserva->cliente)
            ->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee($reserva->inmueble->titulo)
            ->assertSee('Contrato pendiente');
    }
}
