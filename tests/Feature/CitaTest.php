<?php

namespace Tests\Feature;

use App\Enumerados\EstadoCita;
use App\Models\Cita;
use App\Models\CitaHistorial;
use App\Models\ConfigFranjaCita;
use App\Models\Inmueble;
use App\Models\Notificacion;
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
            $this->assertSame('Ya tienes una cita pendiente para este inmueble', $e->errors()['inmueble'][0]);
        }

        // La solicitud nueva nunca queda activa: con InnoDB la transacción se
        // revierte entera; con MyISAM la más reciente queda cancelada
        $this->assertSame(0, Cita::where('cliente_id', $cliente->id)->where('fecha', $this->fechaValida())->where('estado', EstadoCita::Pendiente)->count());
    }

    // ---------------------------------------------------------------
    // HU-10: asignación del administrador
    // ---------------------------------------------------------------

    public function test_hu_10_1_asignar_asesor_activo_notifica_al_asesor(): void
    {
        $this->solicitar(User::factory()->create(), Inmueble::factory()->create());
        $asesor = User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.citas.asignar', Cita::firstOrFail()), ['asesor_id' => $asesor->id]);

        $this->assertSame(EstadoCita::Asignada, Cita::firstOrFail()->estado);
        $this->assertTrue(Notificacion::where('usuario_id', $asesor->id)->where('titulo', 'Cita asignada')->exists());
    }

    public function test_hu_10_2_el_tablero_agrupa_por_asesor_con_fecha_hora_inmueble_y_estado(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs(User::factory()->administrador()->create())
            ->get(route('admin.citas.index'))
            ->assertOk()
            ->assertSee($cita->asesor->nombre)
            ->assertSee($cita->fecha->format('d/m/Y H:i'))
            ->assertSee($cita->inmueble->titulo)
            ->assertSee('Asignada');
    }

    public function test_hu_10_3_reasignar_notifica_al_nuevo_asesor_y_deja_historial(): void
    {
        $cita = $this->citaAsignada();
        $nuevo = User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.citas.asignar', $cita), ['asesor_id' => $nuevo->id]);

        $this->assertSame($nuevo->id, $cita->refresh()->asesor_id);
        $this->assertTrue(Notificacion::where('usuario_id', $nuevo->id)->where('titulo', 'Cita asignada')->exists());
        $this->assertTrue(CitaHistorial::where('cita_id', $cita->id)->where('accion', 'REASIGNADA')->exists());
    }

    public function test_hu_10_3_reasignar_una_cita_confirmada_registra_reasignada(): void
    {
        $cita = $this->citaAsignada();
        $cita->update(['estado' => EstadoCita::Confirmada]);
        $nuevo = User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.citas.asignar', $cita), ['asesor_id' => $nuevo->id]);

        $this->assertTrue(CitaHistorial::where('cita_id', $cita->id)->where('accion', 'REASIGNADA')->exists());
        $this->assertTrue(Notificacion::where('usuario_id', $nuevo->id)->where('titulo', 'Cita asignada')->exists());
        $this->assertSame(EstadoCita::Asignada, $cita->refresh()->estado);
    }

    // ---------------------------------------------------------------
    // HU-11: citas del asesor
    // ---------------------------------------------------------------

    public function test_hu_11_1_el_asesor_ve_sus_citas_y_no_las_ajenas(): void
    {
        $cita = $this->citaAsignada();
        $ajena = $this->citaAsignada();
        $cita->inmueble->update(['titulo' => 'Casa propia de prueba']);
        $ajena->inmueble->update(['titulo' => 'Casa ajena de prueba']);

        $this->actingAs($cita->asesor)
            ->get(route('asesor.citas.index'))
            ->assertOk()
            ->assertSee($cita->inmueble->titulo)
            ->assertDontSee($ajena->inmueble->titulo);
    }

    public function test_hu_11_2_sin_citas_muestra_el_mensaje_de_vacio(): void
    {
        $this->actingAs(User::factory()->asesor()->create())
            ->get(route('asesor.citas.index'))
            ->assertOk()
            ->assertSee('No tienes citas asignadas en este momento');
    }

    public function test_hu_11_3_detalle_con_datos_del_cliente_direccion_notas_y_estado(): void
    {
        $cita = $this->citaAsignada();
        ObservacionCita::guardarPara($cita, $cita->asesor, 'Notas de la visita.');

        $this->actingAs($cita->asesor)
            ->get(route('asesor.citas.show', $cita))
            ->assertOk()
            ->assertSee($cita->cliente->nombre)
            ->assertSee($cita->cliente->telefono)
            ->assertSee($cita->cliente->email)
            ->assertSee($cita->inmueble->direccion)
            ->assertSee('Notas de la visita.')
            ->assertSee('Asignada');
    }

    public function test_hu_11_4_filtro_por_cada_estado(): void
    {
        $asesor = User::factory()->asesor()->create();
        $cliente = User::factory()->create();
        $this->assertSame(['Pendiente', 'Asignada', 'Confirmada', 'Realizada', 'Cancelada'], EstadoCita::valores());

        $titulos = [];
        foreach (EstadoCita::cases() as $estado) {
            $inmueble = Inmueble::factory()->create(['titulo' => "Inmueble de prueba {$estado->value}"]);
            Cita::create(['cliente_id' => $cliente->id, 'asesor_id' => $asesor->id, 'inmueble_id' => $inmueble->id, 'fecha' => $this->fechaValida(), 'estado' => $estado]);
            $titulos[$estado->value] = $inmueble->titulo;
        }

        foreach ($titulos as $estado => $titulo) {
            $respuesta = $this->actingAs($asesor)->get(route('asesor.citas.index', ['estado' => $estado]))->assertOk()->assertSee($titulo);
            foreach ($titulos as $otroEstado => $otroTitulo) {
                if ($otroEstado !== $estado) {
                    $respuesta->assertDontSee($otroTitulo);
                }
            }
        }
    }

    public function test_hu_11_4_el_asesor_confirma_una_cita_asignada_y_se_avisa_al_cliente(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->asesor)->post(route('asesor.citas.confirmar', $cita))->assertSessionHasNoErrors();

        $this->assertSame(EstadoCita::Confirmada, $cita->refresh()->estado);
        $this->assertTrue(Notificacion::where('usuario_id', $cita->cliente_id)->where('titulo', 'Cita confirmada')->exists());
        $this->assertTrue(CitaHistorial::where('cita_id', $cita->id)->where('accion', 'CONFIRMADA')->exists());
    }

    public function test_hu_11_4_otro_asesor_no_confirma_la_cita(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs(User::factory()->asesor()->create())
            ->post(route('asesor.citas.confirmar', $cita))
            ->assertForbidden();

        $this->assertSame(EstadoCita::Asignada, $cita->refresh()->estado);
    }

    public function test_hu_11_4_no_se_confirma_una_cita_que_no_esta_asignada(): void
    {
        $cita = $this->citaAsignada();
        $cita->update(['estado' => EstadoCita::Realizada]);

        $this->actingAs($cita->asesor)->post(route('asesor.citas.confirmar', $cita))->assertSessionHasErrors('estado');
    }

    // ---------------------------------------------------------------
    // HU-12: observaciones y realización
    // ---------------------------------------------------------------

    public function test_hu_12_1_una_cita_confirmada_pasa_a_realizada_con_observacion(): void
    {
        $cita = $this->citaAsignada();
        $cita->update(['estado' => EstadoCita::Confirmada]);

        $this->actingAs($cita->asesor)
            ->post(route('asesor.citas.observacion', $cita), ['observaciones' => 'Todo en orden.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoCita::Realizada, $cita->refresh()->estado);
    }

    public function test_hu_12_2_observacion_vacia_es_rechazada(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->asesor)
            ->post(route('asesor.citas.observacion', $cita), ['observaciones' => ''])
            ->assertSessionHasErrors(['observaciones' => 'Debe ingresar observaciones de la visita antes de marcar como realizada.']);

        $this->assertSame(EstadoCita::Asignada, $cita->refresh()->estado);
    }

    public function test_hu_12_3_y_12_4_se_notifica_al_administrador_y_al_cliente(): void
    {
        $admin = User::factory()->administrador()->create();
        $cita = $this->citaAsignada();

        $this->actingAs($cita->asesor)->post(route('asesor.citas.observacion', $cita), ['observaciones' => 'Visita hecha.']);

        $this->assertTrue(Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Visita completada')->exists());
        $this->assertTrue(Notificacion::where('usuario_id', $cita->cliente_id)->where('titulo', 'Visita completada')->exists());
    }

    // ---------------------------------------------------------------
    // HU-27: el cliente agenda y cancela
    // ---------------------------------------------------------------

    public function test_hu_27_1_la_ficha_ofrece_agendar_cita_y_la_cita_queda_pendiente_sin_asesor_con_aviso_al_admin(): void
    {
        $admin = User::factory()->administrador()->create();
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();

        $this->actingAs($cliente)->get(route('inmuebles.show', $inmueble))->assertSee('Agendar cita')->assertDontSee('Agendar visita');

        $this->solicitar($cliente, $inmueble)->assertSessionHasNoErrors();

        $cita = Cita::firstOrFail();
        $this->assertSame(EstadoCita::Pendiente, $cita->estado);
        $this->assertNull($cita->asesor_id);
        $this->assertTrue(Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Nueva cita por asignar')->exists());
    }

    public function test_hu_27_1_acepta_el_ultimo_dia_de_la_ventana_y_el_primer_hueco_del_dia(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();
        $ultimoDia = now()->addDays(30);
        if ($ultimoDia->isSunday()) {
            $ultimoDia->subDay();
        }

        $this->solicitar($cliente, $inmueble, $ultimoDia->copy()->setTime(17, 30))->assertSessionHasNoErrors();
        $this->assertSame(1, Cita::count());
        $this->assertContains('08:00', ConfigFranjaCita::disponiblesPara($inmueble->id, now()->next('Monday')->format('Y-m-d')));
    }

    public function test_hu_27_1_fuera_de_la_franja_o_de_los_30_dias_es_error(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();
        $martes = $this->fechaValida();

        $this->solicitar($cliente, $inmueble, now()->next('Sunday')->setTime(10, 0))->assertSessionHasErrors('hora_cita');
        $this->solicitar($cliente, $inmueble, $martes->copy()->setTime(18, 30))->assertSessionHasErrors('hora_cita');
        $this->solicitar($cliente, $inmueble, $martes->copy()->setTime(10, 15))->assertSessionHasErrors('hora_cita');
        $this->solicitar($cliente, $inmueble, $martes->copy()->setTime(7, 30))->assertSessionHasErrors('hora_cita');

        $dia31 = now()->addDays(31);
        if ($dia31->isSunday()) {
            $dia31->addDay();
        }
        $this->solicitar($cliente, $inmueble, $dia31->setTime(10, 0))->assertSessionHasErrors('fecha_cita');

        $this->assertSame(0, Cita::count());
    }

    public function test_hu_27_2_dia_sin_franjas_muestra_el_mensaje_de_la_hu(): void
    {
        $this->solicitar(User::factory()->create(), Inmueble::factory()->create(), now()->next('Sunday')->setTime(10, 0))
            ->assertSessionHasErrors(['hora_cita' => 'No hay horarios disponibles para esta fecha. Por favor seleccione otra']);
    }

    public function test_hu_27_2_franja_ocupada_muestra_el_mismo_mensaje(): void
    {
        $inmueble = Inmueble::factory()->create();
        $this->solicitar(User::factory()->create(), $inmueble);

        $this->solicitar(User::factory()->create(), $inmueble)
            ->assertSessionHasErrors(['hora_cita' => 'No hay horarios disponibles para esta fecha. Por favor seleccione otra']);
    }

    public function test_hu_27_3_cita_activa_previa_bloquea_con_el_mensaje_de_la_hu(): void
    {
        $cliente = User::factory()->create();
        $inmueble = Inmueble::factory()->create();
        $this->solicitar($cliente, $inmueble);

        $this->solicitar($cliente, $inmueble, $this->fechaValida()->addDay())
            ->assertSessionHasErrors(['inmueble' => 'Ya tienes una cita pendiente para este inmueble']);

        $this->assertSame(1, Cita::count());
    }

    public function test_hu_27_3_una_cita_confirmada_tambien_cuenta_como_activa(): void
    {
        $cita = $this->citaAsignada();
        $cita->update(['estado' => EstadoCita::Confirmada]);

        $this->solicitar($cita->cliente, $cita->inmueble, $this->fechaValida()->addDay())
            ->assertSessionHasErrors(['inmueble' => 'Ya tienes una cita pendiente para este inmueble']);
    }

    public function test_hu_27_4_cancelar_notifica_al_asesor_asignado_y_al_administrador(): void
    {
        $admin = User::factory()->administrador()->create();
        $cita = $this->citaAsignada();

        $this->actingAs($cita->cliente)->post(route('citas.cancelar', $cita));

        $this->assertSame(EstadoCita::Cancelada, $cita->refresh()->estado);
        $this->assertTrue(Notificacion::where('usuario_id', $cita->asesor_id)->where('titulo', 'Cita cancelada')->exists());
        $this->assertTrue(Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Cita cancelada')->exists());
    }

    public function test_hu_27_4_cancelar_sin_asesor_solo_notifica_al_administrador(): void
    {
        $admin = User::factory()->administrador()->create();
        $asesor = User::factory()->asesor()->create();
        $cliente = User::factory()->create();
        $this->solicitar($cliente, Inmueble::factory()->create());
        $cita = Cita::firstOrFail();

        $this->actingAs($cliente)->post(route('citas.cancelar', $cita));

        $this->assertSame(EstadoCita::Cancelada, $cita->refresh()->estado);
        $this->assertTrue(Notificacion::where('usuario_id', $admin->id)->where('titulo', 'Cita cancelada')->exists());
        $this->assertFalse(Notificacion::where('usuario_id', $asesor->id)->where('titulo', 'Cita cancelada')->exists());
    }

    public function test_hu_27_4_se_puede_cancelar_una_cita_confirmada(): void
    {
        $cita = $this->citaAsignada();
        $cita->update(['estado' => EstadoCita::Confirmada]);

        $this->actingAs($cita->cliente)->post(route('citas.cancelar', $cita))->assertSessionHasNoErrors();

        $this->assertSame(EstadoCita::Cancelada, $cita->refresh()->estado);
    }

    public function test_hu_27_5_mis_citas_muestra_inmueble_fecha_hora_asesor_y_estado(): void
    {
        $cita = $this->citaAsignada();

        $this->actingAs($cita->cliente)
            ->get(route('citas.index'))
            ->assertOk()
            ->assertSee($cita->inmueble->titulo)
            ->assertSee($cita->fecha->format('d/m/Y H:i'))
            ->assertSee($cita->asesor->nombre)
            ->assertSee('Asignada');
    }

    public function test_hu_27_6_el_invitado_ve_agendar_cita_y_va_al_login_con_el_aviso(): void
    {
        $inmueble = Inmueble::factory()->create();

        $this->get(route('inmuebles.show', $inmueble))->assertSee('Agendar cita');

        $this->post(route('citas.store'), ['inmueble_id' => $inmueble->id])
            ->assertRedirect(route('login'))
            ->assertSessionHas('mensaje', 'Debe iniciar sesión para agendar una cita');
    }
}
