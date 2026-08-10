<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\ModalidadInmueble;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\User;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    private function solicitar(User $cliente, Inmueble $inmueble, array $extra = [])
    {
        return $this->actingAs($cliente)->post(route('reservas.store'), array_merge([
            'inmueble_id' => $inmueble->id,
            'acepta_terminos' => '1',
        ], $extra));
    }

    public function test_el_cliente_reserva_un_inmueble_disponible(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->solicitar($cliente, $inmueble)->assertRedirect();

        $reserva = Reserva::firstOrFail();

        $this->assertSame(EstadoReserva::PendientePago, $reserva->estado);
        $this->assertStringStartsWith('RES-', $reserva->codigo_reserva);
    }

    public function test_el_inmueble_sigue_disponible_hasta_que_se_confirme_el_pago(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->solicitar(User::factory()->create(), $inmueble);

        $this->assertSame(EstadoInmueble::Disponible, $inmueble->refresh()->estado);
    }

    public function test_el_monto_se_toma_del_inmueble_y_no_del_formulario(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create(['precio_arrendamiento' => 1_800_000]);

        $this->solicitar(User::factory()->create(), $inmueble, ['monto_reserva' => 1]);

        $this->assertSame('1800000.00', Reserva::firstOrFail()->monto_reserva);
    }

    public function test_un_inmueble_con_reserva_en_proceso_no_admite_otra(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->solicitar(User::factory()->create(), $inmueble);
        $this->solicitar(User::factory()->create(), $inmueble)->assertSessionHasErrors('inmueble');

        $this->assertSame(1, Reserva::count());
    }

    public function test_la_modalidad_ambos_exige_elegir_una(): void
    {
        $inmueble = Inmueble::factory()->create([
            'modalidad' => ModalidadInmueble::Ambos,
            'precio_venta' => 300_000_000,
            'precio_arrendamiento' => 1_500_000,
        ]);

        $this->solicitar(User::factory()->create(), $inmueble)->assertSessionHasErrors('modalidad');

        $this->solicitar(User::factory()->create(), $inmueble, ['modalidad' => ModalidadInmueble::Venta->value])
            ->assertSessionHasNoErrors();

        $this->assertSame('300000000.00', Reserva::firstOrFail()->monto_reserva);
    }

    public function test_hay_que_aceptar_las_condiciones(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('reservas.store'), ['inmueble_id' => $inmueble->id])
            ->assertSessionHasErrors('acepta_terminos');
    }

    public function test_el_cliente_solo_ve_sus_propias_reservas(): void
    {
        $reserva = Reserva::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('reservas.show', $reserva))
            ->assertForbidden();
    }

    public function test_el_cliente_cancela_su_reserva_pendiente(): void
    {
        $reserva = Reserva::factory()->create();

        $this->actingAs($reserva->cliente)
            ->post(route('reservas.cancelar', $reserva))
            ->assertRedirect(route('reservas.index'));

        $this->assertSame(EstadoReserva::Cancelada, $reserva->refresh()->estado);
    }

    public function test_una_reserva_confirmada_no_la_cancela_el_cliente(): void
    {
        $reserva = Reserva::factory()->confirmada()->create();

        $this->actingAs($reserva->cliente)
            ->post(route('reservas.cancelar', $reserva))
            ->assertForbidden();
    }

    public function test_el_comando_expira_las_reservas_vencidas_y_libera_el_inmueble(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Reservado]);
        $reserva = Reserva::factory()->vencida()->create(['inmueble_id' => $inmueble->id]);

        $this->artisan('reservas:expirar')->assertSuccessful();

        $this->assertSame(EstadoReserva::Expirada, $reserva->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $inmueble->refresh()->estado);

        // El cambio automático queda registrado sin autor
        $this->assertDatabaseHas('historial_reserva', [
            'reserva_id' => $reserva->id,
            'estado_nuevo' => EstadoReserva::Expirada->value,
            'cambiado_por' => null,
        ]);
    }
}
