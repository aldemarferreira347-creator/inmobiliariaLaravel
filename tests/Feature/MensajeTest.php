<?php

namespace Tests\Feature;

use App\Models\Conversacion;
use App\Models\Inmueble;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MensajeTest extends TestCase
{
    private function hilo(?User $cliente = null, ?User $asesor = null): Conversacion
    {
        return Conversacion::create([
            'cliente_id' => ($cliente ?? User::factory()->create())->id,
            'asesor_id' => ($asesor ?? User::factory()->asesor()->create())->id,
            'inmueble_id' => Inmueble::factory()->create()->id,
        ]);
    }

    public function test_el_cliente_abre_una_conversacion_desde_la_ficha_del_inmueble(): void
    {
        $cliente = User::factory()->create();
        $asesor = User::factory()->asesor()->create();
        $inmueble = Inmueble::factory()->create();

        $this->actingAs($cliente)
            ->post(route('mensajes.iniciar', $inmueble))
            ->assertRedirect();

        $this->assertDatabaseHas('conversacion', [
            'cliente_id' => $cliente->id,
            'asesor_id' => $asesor->id,
            'inmueble_id' => $inmueble->id,
        ]);
    }

    public function test_el_formulario_de_contacto_de_la_ficha_envia_el_mensaje_de_una_vez(): void
    {
        $cliente = User::factory()->create();
        User::factory()->asesor()->create();
        $inmueble = Inmueble::factory()->create();

        $this->actingAs($cliente)
            ->post(route('mensajes.iniciar', $inmueble), ['mensaje' => '¿Sigue disponible?'])
            ->assertRedirect();

        $conversacion = Conversacion::firstOrFail();

        $this->assertDatabaseHas('mensaje', [
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $cliente->id,
            'contenido' => '¿Sigue disponible?',
        ]);
    }

    public function test_contactar_dos_veces_reutiliza_el_mismo_hilo(): void
    {
        $cliente = User::factory()->create();
        User::factory()->asesor()->create();
        $inmueble = Inmueble::factory()->create();

        $this->actingAs($cliente)->post(route('mensajes.iniciar', $inmueble));
        $this->actingAs($cliente)->post(route('mensajes.iniciar', $inmueble));

        $this->assertSame(1, Conversacion::count());
    }

    public function test_sin_asesores_disponibles_se_avisa_al_cliente(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('mensajes.iniciar', Inmueble::factory()->create()))
            ->assertSessionHasErrors('asesor');
    }

    public function test_se_envia_un_mensaje_con_adjunto(): void
    {
        Storage::fake('public');
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)
            ->post(route('mensajes.store', $conversacion), [
                'contenido' => 'Hola, ¿sigue disponible?',
                'adjunto' => UploadedFile::fake()->image('plano.jpg', 600, 400),
            ])
            ->assertRedirect(route('mensajes.show', $conversacion));

        $mensaje = Mensaje::firstOrFail();

        $this->assertSame('Hola, ¿sigue disponible?', $mensaje->contenido);
        $this->assertTrue($mensaje->tieneAdjunto());
        Storage::disk('public')->assertExists(str_replace('storage/', '', $mensaje->adjunto_url));
    }

    public function test_un_mensaje_vacio_y_sin_adjunto_se_rechaza(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)
            ->post(route('mensajes.store', $conversacion), ['contenido' => ''])
            ->assertSessionHasErrors('contenido');
    }

    public function test_un_tercero_no_entra_en_una_conversacion_ajena(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs(User::factory()->create())
            ->get(route('mensajes.show', $conversacion))
            ->assertForbidden();
    }

    public function test_al_abrir_el_hilo_se_marcan_leidos_los_mensajes_recibidos(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->asesor)
            ->post(route('mensajes.store', $conversacion), ['contenido' => 'Sí, sigue disponible.']);

        $this->assertSame(1, $conversacion->mensajesSinLeerPara($conversacion->cliente));

        $this->actingAs($conversacion->cliente)->get(route('mensajes.show', $conversacion))->assertOk();

        $this->assertSame(0, $conversacion->mensajesSinLeerPara($conversacion->cliente));
    }

    public function test_el_administrador_lee_el_hilo_sin_consumir_los_no_leidos_del_asesor(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)
            ->post(route('mensajes.store', $conversacion), ['contenido' => 'Buenas tardes.']);

        $this->actingAs(User::factory()->administrador()->create())
            ->get(route('mensajes.show', $conversacion))
            ->assertOk();

        $this->assertSame(1, $conversacion->mensajesSinLeerPara($conversacion->asesor));
    }

    public function test_el_sondeo_devuelve_solo_los_mensajes_posteriores(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)->post(route('mensajes.store', $conversacion), ['contenido' => 'Uno']);
        $primero = Mensaje::firstOrFail();

        $this->actingAs($conversacion->cliente)->post(route('mensajes.store', $conversacion), ['contenido' => 'Dos']);

        $this->actingAs($conversacion->asesor)
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde='.$primero->id)
            ->assertOk()
            ->assertJsonCount(1, 'mensajes')
            ->assertJsonPath('mensajes.0.contenido', 'Dos');
    }

    public function test_el_contador_cuenta_solo_los_mensajes_recibidos(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)->post(route('mensajes.store', $conversacion), ['contenido' => 'Hola']);

        $this->actingAs($conversacion->asesor)
            ->getJson(route('mensajes.sin-leer'))
            ->assertOk()
            ->assertJsonPath('mensajes', 1);

        $this->actingAs($conversacion->cliente)
            ->getJson(route('mensajes.sin-leer'))
            ->assertJsonPath('mensajes', 0);
    }
}
