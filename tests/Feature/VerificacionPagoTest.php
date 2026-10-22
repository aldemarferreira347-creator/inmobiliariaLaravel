<?php

namespace Tests\Feature;

use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Servicios\StripeCardService;
use Tests\TestCase;

class VerificacionPagoTest extends TestCase
{
    private function pagoConTarjetaPendiente(): Pago
    {
        $reserva = Reserva::factory()->create(['estado' => EstadoReserva::ProcesandoPago]);

        return Pago::factory()->create([
            'reserva_id' => $reserva->id,
            'metodo_pago' => MetodoPago::TarjetaCredito,
            'estado' => EstadoPago::Procesando,
            'referencia_pasarela' => 'pi_test_1',
        ]);
    }

    public function test_el_dueno_obtiene_el_secreto_para_completar_la_verificacion(): void
    {
        $pago = $this->pagoConTarjetaPendiente();
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldReceive('clientSecretDe')->once()->andReturn('pi_test_1_secret_x'));

        $this->actingAs($pago->reserva->cliente)
            ->postJson(route('reservas.pagos.verificacion', [$pago->reserva, $pago]))
            ->assertOk()
            ->assertJson(['client_secret' => 'pi_test_1_secret_x']);
    }

    public function test_otro_cliente_no_puede_verificar_un_pago_ajeno(): void
    {
        $pago = $this->pagoConTarjetaPendiente();
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldNotReceive('clientSecretDe'));

        $this->actingAs(User::factory()->create())
            ->postJson(route('reservas.pagos.verificacion', [$pago->reserva, $pago]))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('reservas.pagos.sincronizar', [$pago->reserva, $pago]))
            ->assertForbidden();
    }

    public function test_el_pago_debe_pertenecer_a_la_reserva_de_la_ruta(): void
    {
        $pago = $this->pagoConTarjetaPendiente();
        $otra = Reserva::factory()->create(['usuario_id' => $pago->reserva->usuario_id]);
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldNotReceive('sincronizarPago'));

        $this->actingAs($pago->reserva->cliente)
            ->post(route('reservas.pagos.sincronizar', [$otra, $pago]))
            ->assertNotFound();
    }

    public function test_sincronizar_redirige_a_la_reserva_con_el_resultado(): void
    {
        $pago = $this->pagoConTarjetaPendiente();
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldReceive('sincronizarPago')->once()->andReturn('succeeded'));

        $this->actingAs($pago->reserva->cliente)
            ->post(route('reservas.pagos.sincronizar', [$pago->reserva, $pago]))
            ->assertRedirect(route('reservas.show', $pago->reserva));
    }
}
