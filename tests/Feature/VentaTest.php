<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoVenta;
use App\Enumerados\ModalidadInmueble;
use App\Models\Inmueble;
use App\Models\Notificacion;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Servicios\VentaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VentaTest extends TestCase
{
    private function datos(Inmueble $inmueble, User $cliente): array
    {
        return [
            'inmueble_id' => $inmueble->id,
            'usuario_id' => $cliente->id,
            'precio_venta' => 320_000_000,
            'fecha_venta' => now()->format('Y-m-d'),
            'notaria' => 'Notaría 2 de Neiva',
        ];
    }

    public function test_un_cliente_no_accede_al_panel_de_ventas(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('asesor.ventas.index'))
            ->assertForbidden();
    }

    public function test_el_asesor_registra_una_venta_y_el_inmueble_queda_reservado(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();
        $cliente = User::factory()->create();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, $cliente))
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->refresh()->estado);
    }

    public function test_no_se_registra_una_venta_sobre_un_inmueble_ocupado(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Ocupado]);

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, User::factory()->create()))
            ->assertSessionHasErrors('inmueble_id');
    }

    public function test_se_registra_la_venta_de_un_inmueble_reservado_para_compra_por_el_mismo_cliente(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Reservado]);
        $reserva = Reserva::factory()->confirmada()->create([
            'inmueble_id' => $inmueble->id,
            'modalidad' => ModalidadInmueble::Venta,
        ]);

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, $reserva->cliente))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Venta::count());
    }

    public function test_no_se_registra_la_venta_si_lo_reservo_otro_cliente(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Reservado]);
        Reserva::factory()->confirmada()->create([
            'inmueble_id' => $inmueble->id,
            'modalidad' => ModalidadInmueble::Venta,
        ]);

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, User::factory()->create()))
            ->assertSessionHasErrors('inmueble_id');
    }

    public function test_el_comprador_debe_ser_un_cliente(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, User::factory()->asesor()->create()))
            ->assertSessionHasErrors('usuario_id');
    }

    public function test_cerrar_la_venta_ocupa_el_inmueble(): void
    {
        $venta = Venta::factory()->create();
        $venta->inmueble->update(['estado' => EstadoInmueble::Reservado]);

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.cerrar', $venta))
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoVenta::Cerrada, $venta->refresh()->estado);
        $this->assertSame(EstadoInmueble::Ocupado, $venta->inmueble->refresh()->estado);
    }

    public function test_se_sube_la_escritura_de_una_venta_cerrada(): void
    {
        Storage::fake('local');
        $venta = Venta::factory()->create(['estado' => EstadoVenta::Cerrada]);

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.escritura', $venta), [
                'escritura' => UploadedFile::fake()->create('escritura.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($venta->refresh()->tieneEscritura());
    }

    public function test_no_se_sube_la_escritura_de_una_venta_cancelada(): void
    {
        Storage::fake('local');
        $venta = Venta::factory()->create(['estado' => EstadoVenta::Cancelada]);

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.escritura', $venta), [
                'escritura' => UploadedFile::fake()->create('escritura.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_cancelar_la_venta_devuelve_el_inmueble_al_catalogo(): void
    {
        $venta = Venta::factory()->create();
        $venta->inmueble->update(['estado' => EstadoInmueble::Reservado]);

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.cancelar', $venta), ['motivo' => 'El comprador se retiró.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoVenta::Cancelada, $venta->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $venta->inmueble->refresh()->estado);
    }

    // Sin transacciones (MyISAM): otra petición reserva el inmueble entre la lectura y la venta
    public function test_no_se_registra_la_venta_si_otro_reclama_el_inmueble_a_mitad_de_camino(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();
        $reclamado = false;
        Inmueble::retrieved(function (Inmueble $leido) use ($inmueble, &$reclamado) {
            if (! $reclamado && $leido->is($inmueble)) {
                $reclamado = true;
                Inmueble::whereKey($inmueble->id)->update(['estado' => EstadoInmueble::Reservado->value]);
            }
        });

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, User::factory()->create()))
            ->assertSessionHasErrors('inmueble_id');

        $this->assertSame(0, Venta::count());
    }

    // Una petición anterior ya cerró la venta: la cancelación con el modelo viejo no la revive ni libera el inmueble
    public function test_no_se_cancela_una_venta_que_otra_peticion_ya_cerro(): void
    {
        $venta = Venta::factory()->create();
        $venta->inmueble->update(['estado' => EstadoInmueble::Reservado]);
        $obsoleta = Venta::find($venta->id);
        Venta::whereKey($venta->id)->update(['estado' => EstadoVenta::Cerrada->value]);
        Inmueble::whereKey($venta->inmueble_id)->update(['estado' => EstadoInmueble::Ocupado->value]);

        $this->expectException(ValidationException::class);

        try {
            app(VentaService::class)->cancelar($obsoleta, 'Desistimiento.');
        } finally {
            $this->assertSame(EstadoVenta::Cerrada, $venta->refresh()->estado);
            $this->assertSame(EstadoInmueble::Ocupado, $venta->inmueble->refresh()->estado);
        }
    }

    public function test_un_asesor_no_toca_las_ventas_de_otro(): void
    {
        $venta = Venta::factory()->create();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.cerrar', $venta))
            ->assertForbidden();
    }

    public function test_hu_14_1_registrar_venta_queda_en_proceso_y_reserva_el_inmueble(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();
        $cliente = User::factory()->create();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.store'), $this->datos($inmueble, $cliente))
            ->assertSessionHasNoErrors();

        $venta = Venta::firstOrFail();
        $this->assertSame(EstadoVenta::EnProceso, $venta->estado);
        $this->assertSame('En proceso', $venta->estado->etiqueta());
        $this->assertSame(EstadoInmueble::Reservado, $inmueble->refresh()->estado);
    }

    public function test_hu_14_2_la_escritura_la_descargan_administrador_y_cliente_y_nadie_mas(): void
    {
        Storage::fake('local');
        $venta = Venta::factory()->create();

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.escritura', $venta), [
                'escritura' => UploadedFile::fake()->create('escritura.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->administrador()->create())->get(route('ventas.escritura', $venta))->assertOk();
        $this->actingAs($venta->cliente)->get(route('ventas.escritura', $venta))->assertOk();
        $this->actingAs($venta->asesor)->get(route('ventas.escritura', $venta))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('ventas.escritura', $venta))->assertForbidden();
        $this->actingAs(User::factory()->asesor()->create())->get(route('ventas.escritura', $venta))->assertForbidden();
    }

    public function test_hu_14_3_el_historial_del_asesor_solo_lista_sus_ventas(): void
    {
        $asesor = User::factory()->asesor()->create();
        $propia = Venta::factory()->create(['asesor_id' => $asesor->id]);
        $ajena = Venta::factory()->create();

        $this->actingAs($asesor)
            ->get(route('asesor.ventas.index'))
            ->assertOk()
            ->assertSee($propia->inmueble->titulo)
            ->assertSee('En proceso')
            ->assertViewHas('ventas', fn ($ventas) => $ventas->pluck('id')->all() === [$propia->id]);
    }

    public function test_hu_14_4_cancelar_devuelve_disponible_y_notifica_al_cliente(): void
    {
        $venta = Venta::factory()->create();
        $venta->inmueble->update(['estado' => EstadoInmueble::Reservado]);

        $this->actingAs($venta->asesor)
            ->post(route('asesor.ventas.cancelar', $venta), ['motivo' => 'El comprador se retiró.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoInmueble::Disponible, $venta->inmueble->refresh()->estado);
        $this->assertSame(1, Notificacion::where('usuario_id', $venta->usuario_id)->count());
    }

    public function test_hu_14_4_el_administrador_tambien_cancela(): void
    {
        $venta = Venta::factory()->create();
        $venta->inmueble->update(['estado' => EstadoInmueble::Reservado]);

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('asesor.ventas.cancelar', $venta), ['motivo' => 'Desistimiento.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoVenta::Cancelada, $venta->refresh()->estado);
    }
}
