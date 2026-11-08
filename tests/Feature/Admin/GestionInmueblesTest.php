<?php

namespace Tests\Feature\Admin;

use App\Enumerados\EstadoCita;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use App\Models\Cita;
use App\Models\CitaHistorial;
use App\Models\Conversacion;
use App\Models\ImagenInmueble;
use App\Models\Inmueble;
use App\Models\ObservacionCita;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestionInmueblesTest extends TestCase
{
    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Apartamento en El Altico',
            'descripcion' => 'Apartamento amplio y luminoso con excelentes acabados, ubicado en una zona tranquila.',
            'tipo' => TipoInmueble::Apartamento->value,
            'modalidad' => ModalidadInmueble::Venta->value,
            'estado' => EstadoInmueble::Disponible->value,
            'precio_venta' => 320_000_000,
            'ciudad' => 'Neiva',
            'barrio' => 'El Altico',
            'direccion' => 'Calle 10 # 5-20',
            'habitaciones' => 3,
            'banos' => 2,
            'area' => 95,
            'parqueadero' => 1,
        ], $cambios);
    }

    public function test_un_cliente_no_accede_al_panel_de_inmuebles(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.inmuebles.index'))
            ->assertForbidden();
    }

    public function test_el_alta_genera_el_codigo_automaticamente(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.inmuebles.store'), $this->datosValidos())
            ->assertRedirect(route('admin.inmuebles.index'));

        $inmueble = Inmueble::firstOrFail();

        $this->assertStringStartsWith('INM-', $inmueble->codigo);
    }

    public function test_se_rechaza_una_descripcion_demasiado_corta(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.inmuebles.store'), $this->datosValidos(['descripcion' => 'Muy corta.']))
            ->assertSessionHasErrors('descripcion');

        $this->assertSame(0, Inmueble::count());
    }

    public function test_la_modalidad_venta_exige_precio_de_venta(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.inmuebles.store'), $this->datosValidos(['precio_venta' => null]))
            ->assertSessionHasErrors('precio_venta');
    }

    public function test_la_modalidad_ambos_exige_los_dos_precios(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.inmuebles.store'), $this->datosValidos([
                'modalidad' => ModalidadInmueble::Ambos->value,
                'precio_arrendamiento' => null,
            ]))
            ->assertSessionHasErrors('precio_arrendamiento');
    }

    public function test_no_se_puede_marcar_reservado_sin_una_reserva_que_lo_respalde(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();

        $this->actingAs($this->administrador())
            ->put(route('admin.inmuebles.update', $inmueble), $this->datosValidos([
                'estado' => EstadoInmueble::Reservado->value,
            ]))
            ->assertSessionHasErrors('estado');

        $this->assertSame(EstadoInmueble::Disponible, $inmueble->refresh()->estado);
    }

    public function test_no_se_crea_un_inmueble_ocupado_sin_respaldo(): void
    {
        foreach ([EstadoInmueble::Reservado, EstadoInmueble::Ocupado] as $estado) {
            $this->actingAs($this->administrador())
                ->post(route('admin.inmuebles.store'), $this->datosValidos(['estado' => $estado->value]))
                ->assertSessionHasErrors('estado');
        }

        $this->assertSame(0, Inmueble::count());
    }

    public function test_se_puede_marcar_reservado_cuando_existe_una_reserva_en_proceso(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();

        Reserva::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($this->administrador())
            ->put(route('admin.inmuebles.update', $inmueble), $this->datosValidos([
                'estado' => EstadoInmueble::Reservado->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->refresh()->estado);
    }

    public function test_no_se_elimina_un_inmueble_con_reservas_activas(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();

        Reserva::factory()->confirmada()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($this->administrador())
            ->delete(route('admin.inmuebles.destroy', $inmueble))
            ->assertRedirect(route('admin.inmuebles.index'));

        $this->assertDatabaseHas('inmueble', ['id' => $inmueble->id]);
    }

    public function test_al_subir_imagenes_la_primera_queda_como_portada(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrador())
            ->post(route('admin.inmuebles.store'), $this->datosValidos([
                'imagenes' => [
                    UploadedFile::fake()->image('frente.jpg', 800, 600),
                    UploadedFile::fake()->image('sala.jpg', 800, 600),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $inmueble = Inmueble::firstOrFail();

        $this->assertCount(2, $inmueble->imagenes);
        $this->assertSame($inmueble->imagenPrincipal->url, $inmueble->imagen);
    }

    public function test_al_eliminar_la_portada_se_promueve_otra_imagen(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrador())->post(route('admin.inmuebles.store'), $this->datosValidos([
            'imagenes' => [
                UploadedFile::fake()->image('frente.jpg', 800, 600),
                UploadedFile::fake()->image('sala.jpg', 800, 600),
            ],
        ]));

        $inmueble = Inmueble::firstOrFail();
        $portada = $inmueble->imagenPrincipal;

        // Editar dejó de ser una página propia: ahora es un modal en el
        // listado, así que la redirección vuelve ahí (con la pista en
        // sesión para reabrir el modal de este inmueble).
        $this->delete(route('admin.imagenes.destroy', $portada))
            ->assertRedirect(route('admin.inmuebles.index'));

        Storage::disk('public')->assertMissing(str_replace('storage/', '', $portada->url));

        $inmueble->refresh()->load('imagenes');

        $this->assertCount(1, $inmueble->imagenes);
        $this->assertTrue($inmueble->imagenes->first()->es_principal);
        $this->assertSame($inmueble->imagenes->first()->url, $inmueble->imagen);
    }

    public function test_hu_18_4_eliminar_inmueble_borra_favoritos_imagenes_y_citas_sin_fk(): void
    {
        Storage::fake('public');
        $inmueble = Inmueble::factory()->create();
        $clientes = User::factory()->count(2)->create();
        $asesor = User::factory()->create();

        foreach ($clientes as $cliente) {
            DB::table('favorito')->insert(['usuario_id' => $cliente->id, 'inmueble_id' => $inmueble->id]);
        }

        $rutas = [];
        foreach (['uno.jpg', 'dos.jpg'] as $orden => $nombre) {
            Storage::disk('public')->put("inmuebles/{$nombre}", 'contenido');
            $rutas[] = "inmuebles/{$nombre}";
            ImagenInmueble::create(['inmueble_id' => $inmueble->id, 'url' => "storage/inmuebles/{$nombre}", 'es_principal' => $orden === 0, 'orden' => $orden]);
        }

        $cita = Cita::create(['cliente_id' => $clientes[0]->id, 'asesor_id' => $asesor->id, 'inmueble_id' => $inmueble->id, 'fecha' => now()->addDay(), 'estado' => EstadoCita::Asignada]);
        CitaHistorial::registrar($cita, $asesor, 'ASIGNADA', 'Asignada.');
        ObservacionCita::guardarPara($cita, $asesor, 'Observación de la visita.');
        $conversacion = Conversacion::create(['cliente_id' => $clientes[0]->id, 'asesor_id' => $asesor->id, 'inmueble_id' => $inmueble->id]);

        // Simula MyISAM: sin claves foráneas no hay cascadas en la base de datos.
        // SQLite ignora el PRAGMA dentro de una transacción, por eso se cierra la que
        // envuelve la prueba; es seguro porque RefreshDatabase vuelve a migrar la base
        // en memoria al terminar.
        DB::commit();
        Schema::disableForeignKeyConstraints();

        $this->actingAs($this->administrador())
            ->delete(route('admin.inmuebles.destroy', $inmueble))
            ->assertRedirect(route('admin.inmuebles.index'));

        $this->assertDatabaseMissing('inmueble', ['id' => $inmueble->id]);
        $this->assertDatabaseMissing('favorito', ['inmueble_id' => $inmueble->id]);
        $this->assertDatabaseMissing('imageninmueble', ['inmueble_id' => $inmueble->id]);
        $this->assertDatabaseMissing('cita', ['inmueble_id' => $inmueble->id]);
        $this->assertDatabaseMissing('cita_historial', ['cita_id' => $cita->id]);
        $this->assertDatabaseMissing('observacioncita', ['cita_id' => $cita->id]);
        $this->assertDatabaseHas('conversacion', ['id' => $conversacion->id, 'inmueble_id' => null]);

        foreach ($rutas as $ruta) {
            Storage::disk('public')->assertMissing($ruta);
        }
    }

    private function inmuebleConImagen(): array
    {
        Storage::fake('public');
        $inmueble = Inmueble::factory()->deVenta()->create();
        Storage::disk('public')->put('inmuebles/foto.jpg', 'contenido');
        ImagenInmueble::create(['inmueble_id' => $inmueble->id, 'url' => 'storage/inmuebles/foto.jpg', 'es_principal' => true, 'orden' => 0]);

        return [$inmueble, 'inmuebles/foto.jpg'];
    }

    public function test_hu_04_5_reserva_cancelada_impide_eliminar_y_conserva_las_fotos(): void
    {
        [$inmueble, $ruta] = $this->inmuebleConImagen();
        Reserva::factory()->create(['inmueble_id' => $inmueble->id, 'estado' => EstadoReserva::Cancelada]);

        $this->actingAs($this->administrador())
            ->delete(route('admin.inmuebles.destroy', $inmueble))
            ->assertRedirect(route('admin.inmuebles.index'))
            ->assertSessionHas('mensaje', 'No es posible eliminar un inmueble con historial de reservas o ventas');

        $this->assertDatabaseHas('inmueble', ['id' => $inmueble->id]);
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_hu_04_5_venta_impide_eliminar_y_conserva_las_fotos(): void
    {
        [$inmueble, $ruta] = $this->inmuebleConImagen();
        Venta::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($this->administrador())
            ->delete(route('admin.inmuebles.destroy', $inmueble))
            ->assertSessionHas('mensaje', 'No es posible eliminar un inmueble con historial de reservas o ventas');

        $this->assertDatabaseHas('inmueble', ['id' => $inmueble->id]);
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_hu_04_5_reserva_activa_muestra_el_texto_de_la_hu(): void
    {
        [$inmueble] = $this->inmuebleConImagen();
        Reserva::factory()->confirmada()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($this->administrador())
            ->delete(route('admin.inmuebles.destroy', $inmueble))
            ->assertSessionHas('mensaje', 'No es posible eliminar un inmueble con reservas activas');
    }

    public function test_el_modelo_se_niega_a_borrar_un_inmueble_con_historial(): void
    {
        $inmueble = Inmueble::factory()->create();
        Venta::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->expectException(DomainException::class);
        $inmueble->delete();
    }
}
