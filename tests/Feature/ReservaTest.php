<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\ModalidadInmueble;
use App\Mail\Aviso;
use App\Models\Contrato;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\Notificacion;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
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

    public function test_la_reserva_guarda_la_modalidad_elegida(): void
    {
        $ambos = Inmueble::factory()->create([
            'modalidad' => ModalidadInmueble::Ambos,
            'precio_venta' => 300_000_000,
            'precio_arrendamiento' => 1_500_000,
        ]);
        $this->solicitar(User::factory()->create(), $ambos, ['modalidad' => ModalidadInmueble::Arriendo->value]);

        $soloVenta = Inmueble::factory()->deVenta()->create();
        $this->solicitar(User::factory()->create(), $soloVenta);

        $this->assertSame(ModalidadInmueble::Arriendo, Reserva::where('inmueble_id', $ambos->id)->firstOrFail()->modalidad);
        $this->assertSame(ModalidadInmueble::Venta, Reserva::where('inmueble_id', $soloVenta->id)->firstOrFail()->modalidad);
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

    public function test_hu_07_4_dos_reservas_simultaneas_dejan_solo_la_primera(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();
        $inyectada = false;

        // La otra petición inserta su reserva entre la comprobación y el insert propio
        Reserva::creating(function () use ($inmueble, &$inyectada) {
            if ($inyectada) {
                return;
            }
            $inyectada = true;
            Reserva::factory()->create(['inmueble_id' => $inmueble->id]);
        });

        $cliente = User::factory()->create();

        $this->solicitar($cliente, $inmueble)->assertSessionHasErrors('inmueble');

        // Con InnoDB la transacción se revierte entera; con MyISAM queda cancelada
        $this->assertSame(0, Reserva::where('usuario_id', $cliente->id)->whereIn('estado', EstadoReserva::activas())->count());
    }

    // HU-07.1: la reserva nace «Pendiente de pago» y el inmueble no cambia de estado
    public function test_hu_07_1_reserva_exitosa_pendiente_de_pago(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->solicitar(User::factory()->create(), $inmueble)->assertRedirect();

        $this->assertSame(EstadoReserva::PendientePago, Reserva::firstOrFail()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $inmueble->refresh()->estado);
    }

    // HU-07.2: el invitado ve el botón «Reservar» y, al pulsarlo, va al login con el aviso
    public function test_hu_07_2_invitado_reserva_y_es_enviado_al_login(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->get(route('inmuebles.show', $inmueble))
            ->assertOk()
            ->assertSee('Reservar')
            ->assertSee(route('reservas.store'), false)
            ->assertDontSee('Inicia sesión para reservar');

        $this->post(route('reservas.store'), ['inmueble_id' => $inmueble->id, 'acepta_terminos' => '1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('mensaje', 'Debe iniciar sesión para reservar un inmueble');

        $this->assertSame(0, Reserva::count());
    }

    // HU-07.3: un inmueble Reservado u Ocupado no admite la reserva
    public function test_hu_07_3_inmueble_no_disponible(): void
    {
        foreach ([EstadoInmueble::Reservado, EstadoInmueble::Ocupado] as $estado) {
            $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => $estado]);

            $this->solicitar(User::factory()->create(), $inmueble)
                ->assertSessionHasErrors(['inmueble' => 'Este inmueble ya no está disponible']);
        }

        $this->assertSame(0, Reserva::count());
    }

    // HU-07.4: el mismo cliente no puede reservar dos veces el mismo inmueble
    public function test_hu_07_4_reserva_duplicada_del_mismo_cliente(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->solicitar($cliente, $inmueble)->assertSessionDoesntHaveErrors();
        $this->solicitar($cliente, $inmueble)
            ->assertSessionHasErrors(['inmueble' => 'Ya tienes una reserva activa para este inmueble']);

        $this->assertSame(1, Reserva::where('usuario_id', $cliente->id)->count());
    }

    // HU-07.5: cancelar una reserva pendiente la deja «Cancelada» y el inmueble sigue Disponible
    public function test_hu_07_5_cancelar_reserva_pendiente(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();
        $reserva = Reserva::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->actingAs($reserva->cliente)->post(route('reservas.cancelar', $reserva));

        $this->assertSame(EstadoReserva::Cancelada, $reserva->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $inmueble->refresh()->estado);
    }

    // Reserva confirmada hace `$dias` días, con la fila de historial de su confirmación
    private function confirmadaHace(int $dias): Reserva
    {
        $reserva = Reserva::factory()->confirmada()->create([
            'inmueble_id' => Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Reservado])->id,
        ]);

        $historial = HistorialReserva::registrar($reserva, EstadoReserva::ProcesandoPago->value, EstadoReserva::Confirmada->value, 'Pago aprobado.');
        $historial->forceFill(['creado_en' => now()->subDays($dias)])->save();

        return $reserva;
    }

    // HU-09.2 / RN-18: sin contrato a los 7 días de la confirmación, la reserva expira y el inmueble se libera
    public function test_hu_09_2_confirmada_sin_contrato_expira_a_los_7_dias(): void
    {
        $admin = User::factory()->administrador()->create();
        $vencida = $this->confirmadaHace(8);
        $reciente = $this->confirmadaHace(6);
        $conContrato = $this->confirmadaHace(9);
        Contrato::factory()->create(['reserva_id' => $conContrato->id]);

        $this->artisan('reservas:expirar')->assertSuccessful();

        $this->assertSame(EstadoReserva::Expirada, $vencida->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $vencida->inmueble->refresh()->estado);
        $this->assertDatabaseHas('historial_reserva', [
            'reserva_id' => $vencida->id,
            'estado_anterior' => EstadoReserva::Confirmada->value,
            'estado_nuevo' => EstadoReserva::Expirada->value,
            'cambiado_por' => null,
        ]);

        // Cliente y administrador reciben el aviso
        $this->assertSame(1, Notificacion::where('usuario_id', $vencida->usuario_id)->where('titulo', 'Reserva expirada')->count());
        $this->assertSame(1, Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Reserva expirada')->count());

        // Dentro del plazo, o con contrato emitido, no se toca
        $this->assertSame(EstadoReserva::Confirmada, $reciente->refresh()->estado);
        $this->assertSame(EstadoReserva::Confirmada, $conContrato->refresh()->estado);
    }

    // HU-09.4: solo Disponible→Reservado, Reservado→Ocupado y Ocupado→Disponible avisan al administrador
    public function test_hu_09_4_solo_los_cambios_relevantes_avisan_al_administrador(): void
    {
        Mail::fake();
        $admin = User::factory()->administrador()->create();
        $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Disponible]);

        $inmueble->update(['estado' => EstadoInmueble::Reservado]);
        Mail::assertQueuedCount(1);
        $inmueble->update(['estado' => EstadoInmueble::Ocupado]);
        Mail::assertQueuedCount(2);
        $inmueble->update(['estado' => EstadoInmueble::Disponible]);
        Mail::assertQueuedCount(3);

        Mail::assertQueued(Aviso::class, fn (Aviso $correo) => $correo->hasTo($admin->email));

        // Disponible→Ocupado, Ocupado→Reservado y Reservado→Disponible no avisan
        $inmueble->update(['estado' => EstadoInmueble::Ocupado]);
        $inmueble->update(['estado' => EstadoInmueble::Reservado]);
        $inmueble->update(['estado' => EstadoInmueble::Disponible]);
        Mail::assertQueuedCount(3);
    }
}
