<?php

namespace Tests\Feature;

use App\Enumerados\EstadoCita;
use App\Models\Cita;
use App\Models\Inmueble;
use App\Models\ObservacionCita;
use App\Models\User;
use App\Servicios\CitaService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CitaTest extends TestCase
{
    /** Próximo martes a las 10:00: dentro de la franja por defecto (lunes a sábado, 8:00-18:00) y a menos de 30 días */
    private function fechaValida(): Carbon
    {
        return now()->next('Tuesday')->setTime(10, 0);
    }

    private function solicitar(User $cliente, Inmueble $inmueble, ?Carbon $fecha = null)
    {
        $fecha ??= $this->fechaValida();

        return $this->actingAs($cliente)->post(route('citas.store'), [
            'inmueble_id' => $inmueble->id,
            'fecha_cita' => $fecha->format('Y-m-d'),
            'hora_cita' => $fecha->format('H:i'),
        ]);
    }

    private function citaAsignada(): Cita
    {
        return Cita::create([
            'cliente_id' => User::factory()->create()->id,
            'asesor_id' => User::factory()->asesor()->create()->id,
            'inmueble_id' => Inmueble::factory()->create()->id,
            'fecha' => $this->fechaValida(),
            'estado' => EstadoCita::Asignada,
        ]);
    }

    public function test_el_cliente_solicita_una_visita(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();

        $this->solicitar($cliente, $inmueble)->assertSessionHasNoErrors();

        $cita = Cita::firstOrFail();
        $this->assertSame(EstadoCita::Pendiente, $cita->estado);
        $this->assertSame($cliente->id, $cita->cliente_id);
    }

    public function test_dos_clientes_no_agendan_la_misma_franja(): void
    {
        $inmueble = Inmueble::factory()->create();

        $this->solicitar(User::factory()->create(), $inmueble);
        $this->solicitar(User::factory()->create(), $inmueble)->assertSessionHasErrors('hora_cita');

        $this->assertSame(1, Cita::count());
    }

    public function test_una_fecha_de_cita_mal_formada_es_error_de_validacion(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->post(route('citas.store'), [
            'inmueble_id' => Inmueble::factory()->create()->id,
            'fecha_cita' => 'mañana',
            'hora_cita' => 'a las diez',
        ]);

        $respuesta->assertSessionHasErrors(['fecha_cita', 'hora_cita']);
        $this->assertSame(0, Cita::count());
    }

    public function test_no_se_agenda_en_domingo(): void
    {
        $this->solicitar(User::factory()->create(), Inmueble::factory()->create(), now()->next('Sunday')->setTime(10, 0))
            ->assertSessionHasErrors('hora_cita');
    }

    public function test_las_horas_disponibles_excluyen_las_ocupadas(): void
    {
        $inmueble = Inmueble::factory()->create();
        $this->solicitar(User::factory()->create(), $inmueble);

        $horas = $this->actingAs(User::factory()->create())
            ->getJson(route('citas.franjas-disponibles', [
                'inmueble_id' => $inmueble->id,
                'fecha' => $this->fechaValida()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->json('horas');

        $this->assertContains('09:30', $horas);
        $this->assertNotContains('10:00', $horas);
    }

    public function test_el_administrador_asigna_un_asesor(): void
    {
        $this->solicitar(User::factory()->create(), Inmueble::factory()->create());
        $asesor = User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.citas.asignar', Cita::firstOrFail()), ['asesor_id' => $asesor->id])
            ->assertSessionHasNoErrors();

        $cita = Cita::firstOrFail();
        $this->assertSame(EstadoCita::Asignada, $cita->estado);
        $this->assertSame($asesor->id, $cita->asesor_id);
    }

    public function test_el_asesor_registra_la_visita_realizada(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->asesor)
            ->post(route('asesor.citas.observacion', $cita), ['observaciones' => 'El cliente quedó interesado.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoCita::Realizada, $cita->refresh()->estado);
        $this->assertSame('El cliente quedó interesado.', $cita->observacion->descripcion);
    }

    public function test_otro_asesor_no_registra_la_visita(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.citas.observacion', $cita), ['observaciones' => 'Intento ajeno.'])
            ->assertForbidden();
    }

    public function test_no_se_edita_la_observacion_de_una_cita_no_realizada(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->asesor)
            ->patch(route('asesor.citas.observacion.editar', $cita), ['observaciones' => 'Sin haber visitado.'])
            ->assertSessionHasErrors('estado');

        $this->assertSame(0, ObservacionCita::count());
    }

    public function test_el_cliente_cancela_su_visita(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->cliente)->post(route('citas.cancelar', $cita))->assertSessionHasNoErrors();

        $this->assertSame(EstadoCita::Cancelada, $cita->refresh()->estado);
    }

    public function test_otro_cliente_no_cancela_la_visita_ajena(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('citas.cancelar', $this->citaAsignada()))
            ->assertForbidden();
    }

    public function test_hu_27_1_cita_duplicada_simultanea_cancela_la_mas_reciente(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();
        $inyectada = false;

        Cita::creating(function () use ($cliente, $inmueble, &$inyectada) {
            if ($inyectada) {
                return;
            }
            $inyectada = true;
            Cita::create(['cliente_id' => $cliente->id, 'inmueble_id' => $inmueble->id, 'fecha' => now()->addDays(3), 'estado' => EstadoCita::Pendiente]);
        });

        try {
            app(CitaService::class)->solicitar($inmueble, $cliente, $this->fechaValida());
            $this->fail('Debió rechazar la cita duplicada.');
        } catch (ValidationException $e) {
            $this->assertSame('Ya tienes una visita pendiente o asignada para este inmueble.', $e->errors()['inmueble'][0]);
        }

        // La solicitud nueva nunca queda activa: con InnoDB la transacción se
        // revierte entera; con MyISAM la más reciente queda cancelada
        $this->assertSame(0, Cita::where('cliente_id', $cliente->id)->where('fecha', $this->fechaValida())->where('estado', EstadoCita::Pendiente)->count());
    }
}
