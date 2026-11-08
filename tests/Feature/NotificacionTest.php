<?php

namespace Tests\Feature;

use App\Enumerados\EstadoCita;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Enumerados\RolUsuario;
use App\Mail\Aviso;
use App\Mail\ComprobantePago;
use App\Models\Cita;
use App\Models\Conversacion;
use App\Models\Inmueble;
use App\Models\Notificacion;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificacionTest extends TestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'destino' => 'todos',
            'titulo' => 'Mantenimiento programado',
            'mensaje' => 'El sistema no estará disponible el domingo de 2 a 4 de la mañana.',
        ], $cambios);
    }

    public function test_el_usuario_ve_su_centro_de_notificaciones(): void
    {
        $usuario = User::factory()->create();
        Notificacion::create([
            'usuario_id' => $usuario->id,
            'titulo' => 'Aviso',
            'mensaje' => 'Contenido de prueba.',
        ]);

        $this->actingAs($usuario)
            ->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee('Contenido de prueba.');
    }

    public function test_el_cliente_no_recibe_enlaces_al_panel(): void
    {
        $cliente = User::factory()->create();

        foreach (['reserva', 'contrato', 'cita'] as $tipo) {
            Notificacion::create([
                'usuario_id' => $cliente->id,
                'titulo' => "Aviso de {$tipo}",
                'mensaje' => 'Detalle.',
                'tipo' => 'info',
                'referencia_tipo' => $tipo,
                'referencia_id' => 7,
            ]);
        }

        $this->actingAs($cliente)
            ->get(route('notificaciones.index'))
            ->assertOk()
            ->assertDontSee('/admin/')
            ->assertSee(route('reservas.show', 7))
            ->assertSee(route('citas.index'));
    }

    public function test_el_administrador_enlaza_al_detalle_del_panel(): void
    {
        $admin = User::factory()->administrador()->create();
        Notificacion::create([
            'usuario_id' => $admin->id,
            'titulo' => 'Contrato vencido',
            'mensaje' => 'Detalle.',
            'tipo' => 'info',
            'referencia_tipo' => 'contrato',
            'referencia_id' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('notificaciones.index'))
            ->assertSee(route('admin.contratos.show', 3));
    }

    public function test_nadie_marca_notificaciones_ajenas(): void
    {
        $notificacion = Notificacion::create([
            'usuario_id' => User::factory()->create()->id,
            'titulo' => 'Aviso',
            'mensaje' => 'Privado.',
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('notificaciones.leida', $notificacion))
            ->assertForbidden();
    }

    public function test_se_marcan_todas_como_leidas(): void
    {
        $usuario = User::factory()->create();

        foreach (range(1, 3) as $i) {
            Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => "Aviso {$i}", 'mensaje' => '...']);
        }

        $this->actingAs($usuario)->patch(route('notificaciones.leidas'))->assertRedirect();

        $this->assertSame(0, $usuario->notificacionesSinLeer());
        // Marcar como leída conserva las filas (HU-15.4)
        $this->assertSame(3, Notificacion::whereNotNull('leida_en')->count());
    }

    public function test_un_cliente_no_envia_notificaciones_del_sistema(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.notificaciones.store'), $this->datos())
            ->assertForbidden();
    }

    public function test_el_administrador_notifica_a_un_rol_completo(): void
    {
        User::factory()->count(3)->create();
        User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos([
                'destino' => 'rol',
                'rol' => RolUsuario::Cliente->value,
            ]))
            ->assertSessionHasNoErrors();

        // Solo los clientes: ni el asesor ni el propio administrador
        $this->assertSame(3, Notificacion::count());
    }

    public function test_notificar_a_un_rol_exige_indicar_cual(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos(['destino' => 'rol']))
            ->assertSessionHasErrors('rol');
    }

    public function test_la_notificacion_puede_enviarse_tambien_por_correo(): void
    {
        Mail::fake();

        $cliente = User::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos([
                'destino' => 'usuario',
                'usuario_id' => $cliente->id,
                'enviar_correo' => '1',
            ]));

        Mail::assertQueued(Aviso::class, fn (Aviso $correo) => $correo->hasTo($cliente->email));
    }

    public function test_sin_marcar_la_casilla_no_se_envia_correo(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos([
                'destino' => 'usuario',
                'usuario_id' => User::factory()->create()->id,
            ]));

        Mail::assertNothingQueued();
    }

    public function test_al_aprobar_el_pago_el_cliente_recibe_el_comprobante(): void
    {
        Mail::fake();

        $reserva = Reserva::factory()->create();

        $this->actingAs($reserva->cliente)->post(route('reservas.pago', $reserva), [
            'metodo_pago' => MetodoPago::Transferencia->value,
        ]);

        $pago = $reserva->refresh()->pagos()->firstOrFail();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.pagos.revisar', [$reserva, $pago]), ['decision' => 'aprobar']);

        Mail::assertQueued(
            ComprobantePago::class,
            fn (ComprobantePago $correo) => $correo->hasTo($reserva->cliente->email),
        );
    }

    public function test_smtp_caido_no_interrumpe_el_pago(): void
    {
        config([
            'queue.default' => 'sync',
            'mail.default' => 'failover',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $reserva = Reserva::factory()->create();

        $this->actingAs($reserva->cliente)->post(route('reservas.pago', $reserva), [
            'metodo_pago' => MetodoPago::Transferencia->value,
        ]);

        $pago = $reserva->refresh()->pagos()->firstOrFail();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.reservas.pagos.revisar', [$reserva, $pago]), ['decision' => 'aprobar'])
            ->assertRedirect()
            ->assertSessionHas('tipo', 'success');

        $this->assertSame(EstadoReserva::Confirmada, $reserva->refresh()->estado);

        // Los try/catch de los servicios podrían ocultar el fallo: el envío
        // directo demuestra que es el transporte failover quien lo absorbe.
        Mail::to($reserva->cliente->email)->send(new ComprobantePago($pago->fresh()));
        $this->addToAssertionCount(1);
    }

    private function hilo(): Conversacion
    {
        return Conversacion::create([
            'cliente_id' => User::factory()->create()->id,
            'asesor_id' => User::factory()->asesor()->create()->id,
            'inmueble_id' => Inmueble::factory()->create()->id,
        ]);
    }

    public function test_hu_15_1_mensaje_nuevo_notifica_al_asesor_con_cliente_e_inmueble(): void
    {
        $conversacion = $this->hilo();

        $this->actingAs($conversacion->cliente)
            ->post(route('mensajes.store', $conversacion), ['contenido' => 'Hola']);

        $notificacion = Notificacion::where('usuario_id', $conversacion->asesor_id)->firstOrFail();
        $this->assertStringContainsString($conversacion->cliente->nombre, $notificacion->mensaje);
        $this->assertStringContainsString($conversacion->inmueble->titulo, $notificacion->mensaje);
    }

    public function test_hu_15_2_cita_asignada_notifica_al_asesor_con_fecha_hora_e_inmueble(): void
    {
        $fecha = now()->next('Tuesday')->setTime(10, 30);
        $cita = Cita::create([
            'cliente_id' => User::factory()->create()->id,
            'inmueble_id' => Inmueble::factory()->create()->id,
            'fecha' => $fecha,
            'estado' => EstadoCita::Pendiente,
        ]);
        $asesor = User::factory()->asesor()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.citas.asignar', $cita), ['asesor_id' => $asesor->id]);

        $notificacion = Notificacion::where('usuario_id', $asesor->id)->firstOrFail();
        $this->assertStringContainsString($fecha->format('d/m/Y H:i'), $notificacion->mensaje);
        $this->assertStringContainsString($cita->inmueble->titulo, $notificacion->mensaje);
    }

    public function test_hu_15_3_el_centro_lista_leidas_y_no_leidas_marcadas(): void
    {
        $usuario = User::factory()->create();
        Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => 'Aviso nuevo', 'mensaje' => 'A', 'referencia_tipo' => 'cita', 'referencia_id' => 5]);
        Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => 'Aviso viejo', 'mensaje' => 'B', 'leida_en' => now()]);

        $this->actingAs($usuario)
            ->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee('Aviso nuevo')
            ->assertSee('Aviso viejo')
            ->assertSee('data-leida="no"', false)
            ->assertSee('data-leida="si"', false)
            ->assertSee('Leída')
            ->assertSee('No leída')
            ->assertSee(route('citas.index'));
    }

    public function test_hu_15_4_marcar_como_leida_conserva_la_fila_y_baja_el_contador(): void
    {
        $usuario = User::factory()->create();
        $notificacion = Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => 'Aviso', 'mensaje' => 'X']);
        Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => 'Otro', 'mensaje' => 'Y']);

        $this->actingAs($usuario)->patch(route('notificaciones.leida', $notificacion))->assertRedirect();

        $this->assertModelExists($notificacion);
        $this->assertNotNull($notificacion->fresh()->leida_en);
        $this->assertSame(1, $usuario->notificacionesSinLeer());
        $this->getJson(route('mensajes.sin-leer'))->assertJsonPath('notificaciones', 1);
        $this->get(route('notificaciones.index'))->assertSee('Aviso');
    }

    public function test_hu_22_1_envio_individual_es_tipo_sistema_y_visible_para_el_destinatario(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos(['destino' => 'usuario', 'usuario_id' => $cliente->id]));

        $this->assertDatabaseHas('notificacion', ['usuario_id' => $cliente->id, 'tipo' => 'sistema', 'titulo' => 'Mantenimiento programado']);
        $this->actingAs($cliente)->get(route('notificaciones.index'))->assertSee('Mantenimiento programado');
    }

    public function test_hu_22_1_envio_a_todos_crea_una_por_usuario_activo(): void
    {
        User::factory()->count(3)->create();
        User::factory()->inactivo()->create();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.notificaciones.store'), $this->datos());

        $this->assertSame(User::activos()->count(), Notificacion::where('tipo', 'sistema')->count());
        $this->assertSame(4, Notificacion::count());
    }

    public function test_hu_22_1_envio_a_todos_es_solo_in_app_aunque_se_marque_el_correo(): void
    {
        Mail::fake();
        User::factory()->count(200)->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.notificaciones.store'), $this->datos(['enviar_correo' => '1']))
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame(201, Notificacion::where('tipo', 'sistema')->count());
    }

    public function test_hu_15_4_marcar_la_notificacion_de_otro_usuario_da_403_y_no_la_cambia(): void
    {
        $notificacion = Notificacion::create(['usuario_id' => User::factory()->create()->id, 'titulo' => 'Ajena', 'mensaje' => 'X']);

        $this->actingAs(User::factory()->create())
            ->patch(route('notificaciones.leida', $notificacion))
            ->assertForbidden();

        $this->assertNull($notificacion->fresh()->leida_en);
    }

    public function test_hu_22_2_y_22_3_pago_exitoso_notifica_al_cliente_y_alerta_al_administrador(): void
    {
        $admin = User::factory()->administrador()->create();
        $reserva = Reserva::factory()->create();

        $this->actingAs($reserva->cliente)->post(route('reservas.pago', $reserva), ['metodo_pago' => MetodoPago::Transferencia->value]);
        $pago = $reserva->refresh()->pagos()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.reservas.pagos.revisar', [$reserva, $pago]), ['decision' => 'aprobar']);

        $this->assertDatabaseHas('notificacion', ['usuario_id' => $reserva->usuario_id, 'titulo' => 'Reserva confirmada']);
        $this->assertDatabaseHas('notificacion', ['usuario_id' => $admin->id, 'titulo' => 'Nuevo ingreso registrado']);
    }

    public function test_hu_22_4_el_badge_muestra_el_numero_de_no_leidas(): void
    {
        $usuario = User::factory()->create();
        foreach (range(1, 3) as $i) {
            Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => "N{$i}", 'mensaje' => '.']);
        }
        Notificacion::create(['usuario_id' => $usuario->id, 'titulo' => 'Vieja', 'mensaje' => '.', 'leida_en' => now()]);

        $this->actingAs($usuario)
            ->get(route('notificaciones.index'))
            ->assertSeeInOrder(['data-badge-notificaciones', '>3</span>'], false);
        $this->getJson(route('mensajes.sin-leer'))->assertJsonPath('notificaciones', 3);
    }
}
