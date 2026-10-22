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
use App\Models\WebhookEvento;
use App\Servicios\PagoService;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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

    public function test_cancelar_la_reserva_rechaza_el_pago_en_revision(): void
    {
        $pago = $this->reservaConPago();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.cancelar', $pago->reserva), ['motivo' => 'El cliente desistió.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoPago::Rechazado, $pago->refresh()->estado);
        $this->assertSame(EstadoReserva::Cancelada, $pago->reserva->refresh()->estado);
    }

    public function test_aprobar_un_pago_de_una_reserva_cancelada_falla(): void
    {
        $pago = $this->reservaConPago();
        $pago->reserva->update(['estado' => EstadoReserva::Cancelada]);

        $this->expectException(ValidationException::class);

        try {
            app(PagoService::class)->aprobar($pago->refresh());
        } finally {
            $this->assertSame(EstadoReserva::Cancelada, $pago->reserva->refresh()->estado);
            $this->assertSame(EstadoPago::Procesando, $pago->refresh()->estado);
        }
    }

    public function test_un_pago_ya_aprobado_no_se_aprueba_dos_veces(): void
    {
        $pago = $this->reservaConPago();
        $copiaObsoleta = Pago::findOrFail($pago->id);

        app(PagoService::class)->aprobar($pago);

        $this->expectException(ValidationException::class);
        app(PagoService::class)->aprobar($copiaObsoleta);
    }

    public function test_webhook_que_falla_no_queda_marcado_como_procesado(): void
    {
        $pago = $this->reservaConPago();
        $pago->update(['referencia_pasarela' => 'pi_test_123']);
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $evento = json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_123', 'object' => 'payment_intent', 'status' => 'succeeded']],
        ]);
        $firmar = fn () => ['Stripe-Signature' => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$evento, 'whsec_test')];

        // Falla la primera vez y después delega en el servicio real
        $real = app(PagoService::class);
        $intentos = 0;
        $this->mock(PagoService::class, fn ($mock) => $mock->shouldReceive('aprobar')->twice()->andReturnUsing(
            function (Pago $pago) use ($real, &$intentos) {
                if (++$intentos === 1) {
                    throw new RuntimeException('Caída');
                }

                $real->aprobar($pago);
            }
        ));

        $this->call('POST', route('stripe.webhook'), [], [], [], $this->cabeceras($firmar()), $evento)->assertServerError();
        $this->assertNull(WebhookEvento::where('evento_id', 'evt_test_1')->value('procesado_en'));

        // Stripe reintenta: el evento se aplica
        $this->call('POST', route('stripe.webhook'), [], [], [], $this->cabeceras($firmar()), $evento)->assertOk();

        $this->assertSame(EstadoPago::Pagado, $pago->refresh()->estado);
        $this->assertNotNull(WebhookEvento::where('evento_id', 'evt_test_1')->value('procesado_en'));
    }

    /** @param  array<string, string>  $cabeceras */
    private function cabeceras(array $cabeceras): array
    {
        return collect($cabeceras)
            ->mapWithKeys(fn ($valor, $clave) => ['HTTP_'.strtoupper(str_replace('-', '_', $clave)) => $valor])
            ->put('CONTENT_TYPE', 'application/json')
            ->all();
    }
}
