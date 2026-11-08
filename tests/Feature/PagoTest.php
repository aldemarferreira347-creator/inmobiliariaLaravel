<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Mail\ComprobantePago;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\MetodoPagoGuardado;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\WebhookEvento;
use App\Servicios\PagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Tests\Soporte\StripeSimulado;
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

    public function test_un_fallo_de_stripe_sobre_un_pago_ya_no_revisable_no_rompe_el_webhook(): void
    {
        $pago = $this->reservaConPago();
        $pago->update(['referencia_pasarela' => 'pi_test_9']);
        $pago->reserva->update(['estado' => EstadoReserva::Cancelada]);
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $evento = json_encode([
            'id' => 'evt_test_9',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_test_9', 'object' => 'payment_intent', 'status' => 'requires_payment_method']],
        ]);
        $firma = ['Stripe-Signature' => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$evento, 'whsec_test')];

        $this->call('POST', route('stripe.webhook'), [], [], [], $this->cabeceras($firma), $evento)->assertOk();

        $this->assertSame(EstadoReserva::Cancelada, $pago->reserva->refresh()->estado);
    }

    /** @param  array<string, string>  $cabeceras */
    private function cabeceras(array $cabeceras): array
    {
        return collect($cabeceras)
            ->mapWithKeys(fn ($valor, $clave) => ['HTTP_'.strtoupper(str_replace('-', '_', $clave)) => $valor])
            ->put('CONTENT_TYPE', 'application/json')
            ->all();
    }

    /**
     * Simula, sin transacciones ni bloqueos, que otra petición aprueba el pago
     * justo después de que esta lo leyó como «en revisión».
     */
    private function simularAprobacionConcurrente(Pago $pago): void
    {
        $hecho = false;

        Pago::retrieved(function (Pago $leido) use ($pago, &$hecho) {
            if ($hecho || $leido->id !== $pago->id) {
                return;
            }
            $hecho = true;

            DB::table('pago')->where('id', $pago->id)->update(['estado' => EstadoPago::Pagado->value]);
            DB::table('reserva')->where('id', $pago->reserva_id)->update(['estado' => EstadoReserva::Confirmada->value]);
            HistorialReserva::registrar($pago->reserva, EstadoReserva::ProcesandoPago->value, EstadoReserva::Confirmada->value, 'Confirmada por la otra petición.');
        });
    }

    public function test_aprobar_pago_ya_aprobado_no_confirma_dos_veces(): void
    {
        $pago = $this->reservaConPago();
        $obsoleto = Pago::findOrFail($pago->id);
        $this->simularAprobacionConcurrente($pago);

        app(PagoService::class)->aprobar($obsoleto);

        $this->assertSame(1, HistorialReserva::where('reserva_id', $pago->reserva_id)
            ->where('estado_nuevo', EstadoReserva::Confirmada->value)->count());
        $this->assertSame(0, Notificacion::where('titulo', 'Reserva confirmada')->count());
        $this->assertSame(0, Notificacion::where('titulo', 'Nuevo ingreso registrado')->count());
    }

    public function test_rechazar_pago_ya_revisado_no_registra_un_segundo_cambio(): void
    {
        $pago = $this->reservaConPago();
        $obsoleto = Pago::findOrFail($pago->id);
        $this->simularAprobacionConcurrente($pago);

        app(PagoService::class)->rechazar($obsoleto, null, 'Motivo tardío');

        $this->assertSame(EstadoPago::Pagado, $pago->refresh()->estado);
        $this->assertSame(EstadoReserva::Confirmada, $pago->reserva->refresh()->estado);
        $this->assertSame(0, Notificacion::where('titulo', 'Pago rechazado')->count());
    }

    public function test_aprobar_no_confirma_una_reserva_cancelada_a_mitad_de_camino(): void
    {
        $pago = $this->reservaConPago();
        $obsoleto = Pago::findOrFail($pago->id);
        $cancelada = false;

        // Otra petición cancela la reserva justo después de ganar este pago el CAS
        Pago::retrieved(function (Pago $leido) use ($pago, &$cancelada) {
            if ($cancelada || $leido->id !== $pago->id || $leido->estado !== EstadoPago::Pagado) {
                return;
            }
            $cancelada = true;

            DB::table('reserva')->where('id', $pago->reserva_id)->update(['estado' => EstadoReserva::Cancelada->value]);
        });

        try {
            app(PagoService::class)->aprobar($obsoleto);
            $this->fail('Debió fallar: la reserva ya no estaba esperando el pago.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Notificacion::where('titulo', 'Reserva confirmada')->count());
        $this->assertNotSame(EstadoInmueble::Reservado, $pago->reserva->inmueble->refresh()->estado);
        $this->assertSame(0, HistorialReserva::where('reserva_id', $pago->reserva_id)
            ->where('estado_nuevo', EstadoReserva::Confirmada->value)->count());
    }

    // HU-23.1: el pago con tarjeta se confirma en la misma petición, sin esperar al webhook
    // (InfinityFree bloquea los webhooks entrantes de Stripe).
    public function test_hu_23_1_pago_exitoso_sin_webhook(): void
    {
        Mail::fake();

        // Stripe simulado en la capa HTTP: el servicio real corre completo y no hay webhook.
        ApiRequestor::setHttpClient(new class implements ClientInterface
        {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return [json_encode(['id' => 'pi_test_sinwebhook', 'object' => 'payment_intent', 'status' => 'succeeded']), 200, []];
            }
        });

        try {
            $reserva = Reserva::factory()->create(['inmueble_id' => Inmueble::factory()->deArriendo()->create()->id]);
            $tarjeta = MetodoPagoGuardado::create([
                'cliente_id' => $reserva->cliente->id,
                'token_pasarela' => 'pm_test_1',
                'cliente_pasarela_id' => 'cus_test_1',
                'marca' => 'visa',
                'ultimos_4' => '4242',
            ]);

            $this->actingAs($reserva->cliente)
                ->post(route('reservas.pagar-con-tarjeta', [$reserva, $tarjeta]))
                ->assertRedirect(route('reservas.show', $reserva))
                ->assertSessionHas('tipo', 'success');
        } finally {
            ApiRequestor::setHttpClient(null);
        }

        $this->assertSame(EstadoReserva::Confirmada, $reserva->refresh()->estado);
        $this->assertSame(EstadoInmueble::Reservado, $reserva->inmueble->refresh()->estado);
        $this->assertSame(EstadoPago::Pagado, $reserva->pagos()->firstOrFail()->estado);
        $this->assertSame(0, WebhookEvento::count());
        Mail::assertQueued(
            ComprobantePago::class,
            fn (ComprobantePago $correo) => $correo->hasTo($reserva->cliente->email),
        );
    }

    /** @return array{0: Reserva, 1: MetodoPagoGuardado} */
    private function reservaConTarjetaGuardada(string $token = 'pm_test_1'): array
    {
        $reserva = Reserva::factory()->create(['inmueble_id' => Inmueble::factory()->deArriendo()->create()->id]);
        $tarjeta = MetodoPagoGuardado::create([
            'cliente_id' => $reserva->cliente->id,
            'token_pasarela' => $token,
            'cliente_pasarela_id' => 'cus_test_1',
            'marca' => 'Visa',
            'ultimos_4' => '4242',
        ]);

        return [$reserva, $tarjeta];
    }

    private function pagarConTarjeta(Reserva $reserva, MetodoPagoGuardado $tarjeta)
    {
        return $this->actingAs($reserva->cliente)->post(route('reservas.pagar-con-tarjeta', [$reserva, $tarjeta]));
    }

    private function assertReservaSinConfirmar(Reserva $reserva): void
    {
        $this->assertSame(EstadoReserva::PendientePago, $reserva->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $reserva->inmueble->refresh()->estado);
    }

    // HU-23.2: la entidad rechaza la tarjeta
    public function test_hu_23_2_pago_rechazado_por_el_banco(): void
    {
        [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada();
        StripeSimulado::instalar(fn () => StripeSimulado::errorDeTarjeta('card_declined', 'Your card was declined.'));

        try {
            $this->pagarConTarjeta($reserva, $tarjeta)
                ->assertRedirect(route('reservas.show', $reserva))
                ->assertSessionHas('mensaje', 'Pago rechazado. Intente con otro método de pago')
                ->assertSessionHas('tipo', 'error');
        } finally {
            StripeSimulado::desinstalar();
        }

        $this->assertReservaSinConfirmar($reserva);
        $this->assertSame(EstadoPago::Rechazado, $reserva->pagos()->firstOrFail()->estado);
    }

    // HU-23.3: sin conexión con la pasarela se ofrece «Reintentar pago» y la reserva no cambia
    public function test_hu_23_3_error_de_conexion_ofrece_reintentar(): void
    {
        Exceptions::fake();
        [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada();
        StripeSimulado::instalar(fn () => throw new ApiConnectionException('cURL error 6: no se pudo resolver el host'));

        try {
            $this->pagarConTarjeta($reserva, $tarjeta)
                ->assertRedirect(route('reservas.show', $reserva))
                ->assertSessionHas('tipo', 'error')
                ->assertSessionHas('reintentar_tarjeta', $tarjeta->id);
        } finally {
            StripeSimulado::desinstalar();
        }

        $this->assertReservaSinConfirmar($reserva);
        Exceptions::assertReported(ApiConnectionException::class);

        // Al volver a la reserva aparece el botón y no se filtra el detalle técnico
        $this->followingRedirects()
            ->actingAs($reserva->cliente)
            ->withSession(['reintentar_tarjeta' => $tarjeta->id, 'mensaje' => 'No pudimos comunicarnos con la pasarela de pago. Intente nuevamente.', 'tipo' => 'error'])
            ->get(route('reservas.show', $reserva))
            ->assertSee('Reintentar pago')
            ->assertDontSee('cURL error');
    }

    // HU-23.4: datos de tarjeta inválidos no generan ningún pago
    public function test_hu_23_4_datos_de_tarjeta_invalidos(): void
    {
        foreach (['incorrect_number', 'invalid_expiry_month', 'invalid_expiry_year', 'invalid_cvc', 'incorrect_cvc'] as $codigo) {
            [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada("pm_{$codigo}");
            StripeSimulado::instalar(fn () => StripeSimulado::errorDeTarjeta($codigo));

            try {
                $this->pagarConTarjeta($reserva, $tarjeta)
                    ->assertSessionHas('mensaje', 'Datos inválidos. Verifique la información de su tarjeta')
                    ->assertSessionHas('tipo', 'error');
            } finally {
                StripeSimulado::desinstalar();
            }

            $this->assertReservaSinConfirmar($reserva);
        }

        $this->assertSame(0, Pago::count());
    }

    // HU-23.5: una falla interna no filtra detalles, ofrece reintentar y deja la reserva pendiente
    public function test_hu_23_5_error_interno_al_procesar_el_pago(): void
    {
        Exceptions::fake();
        [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada();
        StripeSimulado::instalar(fn () => throw new RuntimeException('SQLSTATE secreto interno'));

        try {
            $this->pagarConTarjeta($reserva, $tarjeta)
                ->assertRedirect(route('reservas.show', $reserva))
                ->assertSessionHas('tipo', 'error')
                ->assertSessionHas('reintentar_tarjeta', $tarjeta->id)
                ->assertSessionMissing('errors');
        } finally {
            StripeSimulado::desinstalar();
        }

        $this->assertStringNotContainsString('SQLSTATE', (string) session('mensaje'));
        $this->assertReservaSinConfirmar($reserva);
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame(0, Notificacion::where('mensaje', 'like', '%SQLSTATE%')->count());
        $this->assertSame(0, HistorialReserva::where('comentario', 'like', '%SQLSTATE%')->count());
    }

    // HU-20.1: solo se guardan token, últimos 4 y marca; ninguna columna admite el número completo
    public function test_hu_20_1_guardar_tarjeta_solo_token_ultimos4_y_marca(): void
    {
        $cliente = User::factory()->create();

        StripeSimulado::instalar(fn () => StripeSimulado::ok([
            'id' => 'pm_test_nueva', 'object' => 'payment_method', 'customer' => 'cus_test_1',
            'billing_details' => ['name' => 'Titular Prueba'],
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
        ]));

        try {
            $this->actingAs($cliente)
                ->postJson(route('perfil.tarjetas.store'), ['payment_method_id' => 'pm_test_nueva', 'customer_id' => 'cus_test_1'])
                ->assertOk();
        } finally {
            StripeSimulado::desinstalar();
        }

        $tarjeta = MetodoPagoGuardado::firstOrFail();
        $this->assertSame('pm_test_nueva', $tarjeta->token_pasarela);
        $this->assertSame('4242', $tarjeta->ultimos_4);
        $this->assertSame('Visa', $tarjeta->marca);

        // Ningún valor guardado se parece a un número de tarjeta (13 a 19 dígitos)
        foreach ($tarjeta->getAttributes() as $valor) {
            $this->assertDoesNotMatchRegularExpression('/\d{13,19}/', (string) $valor);
        }

        // Ninguna columna puede alojar el PAN, el CVV ni la expiración completa
        $columnas = collect(Schema::getColumns('metodo_pago_guardado'))->keyBy('name');
        foreach ($columnas->keys() as $nombre) {
            $this->assertDoesNotMatchRegularExpression('/numero|pan|cvv|cvc|card_number|tarjeta/i', $nombre);
        }
        $this->assertMatchesRegularExpression('/^(var)?char(\(4\))?$/i', $columnas['ultimos_4']['type']);
        $this->assertMatchesRegularExpression('/^varchar(\(20\))?$/i', $columnas['marca']['type']);
    }

    // HU-20.2: el listado muestra marca y últimos 4
    public function test_hu_20_2_listado_de_tarjetas_con_marca_y_ultimos_4(): void
    {
        [$reserva] = $this->reservaConTarjetaGuardada();

        $this->actingAs($reserva->cliente)
            ->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee('Visa')
            ->assertSee('4242');
    }

    // HU-20.3: eliminar una tarjeta la quita de las opciones de pago
    public function test_hu_20_3_eliminar_tarjeta(): void
    {
        [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada();
        StripeSimulado::instalar(fn () => StripeSimulado::ok(['id' => 'pm_test_1', 'object' => 'payment_method']));

        try {
            $this->actingAs($reserva->cliente)
                ->delete(route('perfil.tarjetas.destroy', $tarjeta))
                ->assertRedirect(route('perfil.edit'))
                ->assertSessionHas('mensaje', 'Tarjeta eliminada correctamente.');
        } finally {
            StripeSimulado::desinstalar();
        }

        $this->assertFalse($tarjeta->refresh()->activo);

        $this->actingAs($reserva->cliente)->get(route('reservas.show', $reserva))->assertDontSee('4242');
    }

    // HU-20.4: pagar con la tarjeta guardada no pide ni envía datos de tarjeta
    public function test_hu_20_4_pago_con_tarjeta_guardada_sin_pedir_datos(): void
    {
        Mail::fake();
        [$reserva, $tarjeta] = $this->reservaConTarjetaGuardada();
        $stripe = StripeSimulado::instalar(fn () => StripeSimulado::ok(['id' => 'pi_test_guardada', 'object' => 'payment_intent', 'status' => 'succeeded']));

        try {
            $this->pagarConTarjeta($reserva, $tarjeta)->assertSessionHas('tipo', 'success');
        } finally {
            StripeSimulado::desinstalar();
        }

        $this->assertCount(1, $stripe->peticiones);
        $this->assertSame('pm_test_1', $stripe->peticiones[0]['params']['payment_method']);
        $this->assertArrayNotHasKey('card', $stripe->peticiones[0]['params']);
        $this->assertSame(EstadoReserva::Confirmada, $reserva->refresh()->estado);
    }
}
