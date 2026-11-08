<?php

namespace Tests\Feature;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Models\Contrato;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Servicios\PagoService;
use App\Servicios\StripeCardService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Planificador por petición: sin cron, cola ni webhooks (InfinityFree).
 * Las tareas corren en terminate(), tras enviar la respuesta.
 */
class TareasPendientesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.tareas_por_peticion' => true]);
        // Evita que el conciliador consulte a Stripe de verdad en tests que no lo mockean
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldReceive('sincronizarPago')->andReturn('processing')->byDefault());
    }

    public function test_hu_09_2_reserva_vencida_se_libera_en_la_siguiente_visita(): void
    {
        // reservas:expirar vence las reservas PendientePago con plazo de pago agotado
        $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Disponible]);
        $reserva = Reserva::factory()->vencida()->create(['inmueble_id' => $inmueble->id]);

        $this->get(route('inicio'))->assertOk();

        $this->assertSame(EstadoReserva::Expirada, $reserva->fresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $inmueble->fresh()->estado);
    }

    public function test_hu_17_3_contrato_vencido_en_la_siguiente_visita(): void
    {
        $admin = User::factory()->administrador()->create();
        $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Reservado]);
        $reserva = Reserva::factory()->confirmada()->create(['inmueble_id' => $inmueble->id]);
        $contrato = Contrato::factory()->vencido()->create(['reserva_id' => $reserva->id]);

        $this->get('/')->assertOk();

        $this->assertSame(EstadoContrato::Vencido, $contrato->fresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $inmueble->fresh()->estado);
        $this->assertTrue(Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Contrato vencido')->exists());
    }

    public function test_tareas_se_ejecutan_una_vez_cada_15_minutos(): void
    {
        $reserva = Reserva::factory()->vencida()->create();
        $expiraciones = fn () => HistorialReserva::where('estado_nuevo', EstadoReserva::Expirada->value)->count();

        $this->get('/')->assertOk();
        $this->assertSame(1, $expiraciones());

        // Segunda reserva vencida dentro de la ventana: no se procesa todavía
        Reserva::factory()->vencida()->create();
        $this->get('/')->assertOk();
        $this->assertSame(1, $expiraciones());

        $this->travel(16)->minutes();
        $this->get('/')->assertOk();
        $this->assertSame(2, $expiraciones());
        $this->assertSame(EstadoReserva::Expirada, $reserva->fresh()->estado);
    }

    public function test_fallo_de_tarea_no_rompe_la_pagina(): void
    {
        Exceptions::fake();
        // Sustituye el comando real por uno que lanza (registerCommand reemplaza por firma)
        $this->app[Kernel::class]->registerCommand(new class extends Command
        {
            protected $signature = 'contratos:vencer';

            public function handle(): int
            {
                throw new RuntimeException('falla simulada');
            }
        });
        $reserva = Reserva::factory()->vencida()->create();
        $pago = $this->pagoProcesandoDeHace(20);
        $this->mock(StripeCardService::class, function ($mock) use ($pago) {
            $mock->shouldReceive('sincronizarPago')->once()->with(Mockery::on(fn ($p) => $p->is($pago)))->andReturn('processing');
        });

        $this->get('/')->assertOk();

        // La excepción se reportó (camino del catch) y las tareas posteriores corrieron
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'falla simulada');
        $this->assertSame(EstadoReserva::Expirada, $reserva->fresh()->estado);
    }

    public function test_pago_3ds_abandonado_se_concilia_sin_webhook(): void
    {
        $pago = $this->pagoProcesandoDeHace(20);
        $this->mock(StripeCardService::class, function ($mock) use ($pago) {
            $mock->shouldReceive('sincronizarPago')->once()->andReturnUsing(function () use ($pago) {
                app(PagoService::class)->aprobar($pago);

                return 'succeeded';
            });
        });

        $this->get('/')->assertOk();

        $this->assertSame(EstadoReserva::Confirmada, $pago->reserva->fresh()->estado);
        $this->assertSame(EstadoInmueble::Reservado, $pago->reserva->inmueble->fresh()->estado);
    }

    public function test_pago_reciente_no_se_concilia_todavia(): void
    {
        $this->pagoProcesandoDeHace(3);
        $this->mock(StripeCardService::class, fn ($mock) => $mock->shouldNotReceive('sincronizarPago'));

        $this->get('/')->assertOk();
    }

    public function test_la_ruta_del_webhook_no_dispara_tareas(): void
    {
        $reserva = Reserva::factory()->vencida()->create();

        $this->post('stripe/webhook');

        $this->assertSame(EstadoReserva::PendientePago, $reserva->fresh()->estado);
    }

    public function test_con_tareas_desactivadas_no_se_ejecuta_nada(): void
    {
        config(['app.tareas_por_peticion' => false]);
        $reserva = Reserva::factory()->vencida()->create();

        $this->get('/')->assertOk();

        $this->assertSame(EstadoReserva::PendientePago, $reserva->fresh()->estado);
    }

    private function pagoProcesandoDeHace(int $minutos): Pago
    {
        $reserva = Reserva::factory()->create(['estado' => EstadoReserva::ProcesandoPago]);
        $pago = Pago::factory()->create([
            'reserva_id' => $reserva->id,
            'metodo_pago' => MetodoPago::TarjetaCredito,
            'estado' => EstadoPago::Procesando,
            'referencia_pasarela' => 'pi_test_'.$reserva->id,
        ]);
        $pago->forceFill(['created_at' => now()->subMinutes($minutos)])->save();

        return $pago;
    }
}
