<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Models\Inmueble;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Tests\TestCase;

class PagoTest extends TestCase
{
    private function reservaConPago(): Pago
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();
        $reserva = Reserva::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($reserva->cliente)->post(route('reservas.pago', $reserva), [
            'metodo_pago' => MetodoPago::Transferencia->value,
            'referencia' => 'REF-123456',
        ]);

        return $reserva->refresh()->pagos()->firstOrFail();
    }

    public function test_el_cliente_registra_el_pago_y_queda_en_revision(): void
    {
        $pago = $this->reservaConPago();

        $this->assertSame(EstadoPago::Procesando, $pago->estado);
        $this->assertSame(EstadoReserva::ProcesandoPago, $pago->reserva->estado);
    }

    public function test_el_monto_del_pago_lo_fija_la_reserva(): void
    {
        $pago = $this->reservaConPago();

        $this->assertSame($pago->reserva->monto_reserva, $pago->monto);
    }

    public function test_no_se_registran_dos_pagos_a_la_vez(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs($pago->reserva->cliente)
            ->post(route('reservas.pago', $pago->reserva), ['metodo_pago' => MetodoPago::Efectivo->value])
            ->assertForbidden();

        $this->assertSame(1, $pago->reserva->pagos()->count());
    }

    public function test_al_aprobar_el_pago_la_reserva_se_confirma_y_el_inmueble_queda_reservado(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.pagos.revisar', [$pago->reserva, $pago]), ['decision' => 'aprobar'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoPago::Pagado, $pago->refresh()->estado);
        $this->assertSame(EstadoReserva::Confirmada, $pago->reserva->refresh()->estado);
        $this->assertSame(EstadoInmueble::Reservado, $pago->reserva->inmueble->refresh()->estado);
    }

    public function test_al_rechazar_el_pago_la_reserva_vuelve_a_pendiente(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.pagos.revisar', [$pago->reserva, $pago]), [
                'decision' => 'rechazar',
                'motivo_rechazo' => 'La referencia no coincide.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoPago::Rechazado, $pago->refresh()->estado);
        $this->assertSame(EstadoReserva::PendientePago, $pago->reserva->refresh()->estado);
        $this->assertTrue($pago->reserva->refresh()->admiteNuevoPago());
    }

    public function test_rechazar_exige_indicar_el_motivo(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.pagos.revisar', [$pago->reserva, $pago]), ['decision' => 'rechazar'])
            ->assertSessionHasErrors('motivo_rechazo');
    }

    public function test_un_cliente_no_puede_revisar_pagos(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.reservas.pagos.revisar', [$pago->reserva, $pago]), ['decision' => 'aprobar'])
            ->assertForbidden();
    }
}
