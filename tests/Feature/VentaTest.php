<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoVenta;
use App\Models\Inmueble;
use App\Models\User;
use App\Models\Venta;
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

    public function test_un_asesor_no_toca_las_ventas_de_otro(): void
    {
        $venta = Venta::factory()->create();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.ventas.cerrar', $venta))
            ->assertForbidden();
    }
}
