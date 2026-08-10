<?php

namespace Tests\Feature;

use App\Enumerados\MetodoPago;
use App\Enumerados\RolUsuario;
use App\Mail\Aviso;
use App\Mail\ComprobantePago;
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
}
