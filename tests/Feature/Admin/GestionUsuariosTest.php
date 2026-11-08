<?php

namespace Tests\Feature\Admin;

use App\Enumerados\EstadoCita;
use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Inmueble;
use App\Models\Permiso;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_un_visitante_no_accede_al_panel(): void
    {
        $this->get(route('admin.usuarios.index'))->assertRedirect(route('login'));
    }

    public function test_un_cliente_no_accede_al_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();
    }

    public function test_el_administrador_ve_el_listado(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('admin.usuarios.index'))
            ->assertOk();
    }

    public function test_el_administrador_crea_un_asesor(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.usuarios.store'), [
                'nombre' => 'Carlos Rojas',
                'email' => 'carlos@ejemplo.test',
                'contrasena' => 'Password1*',
                'rol' => RolUsuario::Asesor->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('usuario', [
            'email' => 'carlos@ejemplo.test',
            'rol' => RolUsuario::Asesor->value,
        ]);
    }

    public function test_el_administrador_cambia_el_rol_de_otro_usuario(): void
    {
        $objetivo = User::factory()->create();

        $this->actingAs($this->administrador())
            ->patch(route('admin.usuarios.rol', $objetivo), ['rol' => RolUsuario::Asesor->value])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame(RolUsuario::Asesor, $objetivo->refresh()->rol);
    }

    public function test_el_administrador_no_puede_cambiarse_el_rol_a_si_mismo(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.rol', $admin), ['rol' => RolUsuario::Cliente->value])
            ->assertForbidden();

        $this->assertSame(RolUsuario::Administrador, $admin->refresh()->rol);
    }

    public function test_desactivar_un_usuario_queda_registrado_en_auditoria(): void
    {
        $objetivo = User::factory()->create();

        $this->actingAs($this->administrador())
            ->patch(route('admin.usuarios.estado', $objetivo))
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame(EstadoUsuario::Inactivo, $objetivo->refresh()->estado);
        $this->assertNotNull($objetivo->desactivado_en);

        $this->assertTrue(
            Auditoria::where('entidad', 'usuario')->where('entidad_id', $objetivo->id)->exists()
        );
    }

    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.estado', $admin))
            ->assertForbidden();

        $this->assertSame(EstadoUsuario::Activo, $admin->refresh()->estado);
    }

    public function test_el_administrador_elimina_un_usuario_sin_historial(): void
    {
        $objetivo = User::factory()->create();

        $this->actingAs($this->administrador())
            ->delete(route('admin.usuarios.destroy', $objetivo))
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseMissing('usuario', ['id' => $objetivo->id]);
    }

    public function test_el_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('usuario', ['id' => $admin->id]);
    }

    public function test_la_matriz_de_permisos_es_accesible_para_el_administrador(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('admin.permisos.index'))
            ->assertOk();
    }

    /** @return array<string, array{0: RolUsuario, 1: string}> */
    public static function rolesConPanel(): array
    {
        return [
            'asesor' => [RolUsuario::Asesor, 'asesor.ventas.index'],
            'cliente' => [RolUsuario::Cliente, 'inicio'],
            'administrador' => [RolUsuario::Administrador, 'admin.inmuebles.index'],
        ];
    }

    #[DataProvider('rolesConPanel')]
    public function test_hu_16_1_26_2_usuario_creado_inicia_sesion_y_llega_a_su_panel(RolUsuario $rol, string $panel): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.usuarios.store'), [
                'nombre' => 'Persona Nueva',
                'email' => 'nueva@ejemplo.test',
                'contrasena' => 'Clave1234',
                'rol' => $rol->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('usuario', ['email' => 'nueva@ejemplo.test', 'rol' => $rol->value]);

        auth()->logout();

        $this->post(route('login'), ['email' => 'nueva@ejemplo.test', 'password' => 'Clave1234'])
            ->assertRedirect(route($panel));

        $this->assertAuthenticated();
        $this->assertSame($rol, auth()->user()->rol);
        $this->get(route($panel))->assertOk();
    }

    public function test_hu_16_1_la_contrasena_debe_cumplir_la_regla_de_seguridad(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('admin.usuarios.store'), [
                'nombre' => 'Persona Nueva',
                'email' => 'nueva@ejemplo.test',
                'contrasena' => 'sololetras',
                'rol' => RolUsuario::Asesor->value,
            ])
            ->assertSessionHasErrors('contrasena');

        $this->assertDatabaseMissing('usuario', ['email' => 'nueva@ejemplo.test']);
    }

    public function test_hu_16_2_26_3_usuario_desactivado_no_inicia_sesion_y_conserva_reservas_y_citas(): void
    {
        $cliente = User::factory()->create();
        $reserva = Reserva::factory()->create(['usuario_id' => $cliente->id]);
        $cita = Cita::create([
            'cliente_id' => $cliente->id,
            'asesor_id' => User::factory()->asesor()->create()->id,
            'inmueble_id' => Inmueble::factory()->create()->id,
            'fecha' => now()->addDays(3)->setTime(10, 0),
            'estado' => EstadoCita::Asignada,
        ]);

        $this->actingAs($this->administrador())
            ->patch(route('admin.usuarios.estado', $cliente))
            ->assertRedirect(route('admin.usuarios.index'));

        auth()->logout();

        $this->post(route('login'), ['email' => $cliente->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Su cuenta está inactiva. Contacte al administrador']);
        $this->assertGuest();

        $this->assertDatabaseHas('reserva', ['id' => $reserva->id, 'usuario_id' => $cliente->id]);
        $this->assertDatabaseHas('cita', ['id' => $cita->id, 'cliente_id' => $cliente->id]);
    }

    public function test_hu_26_1_cambiar_el_rol_aplica_los_permisos_nuevos_en_la_siguiente_peticion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('asesor.citas.index'))->assertForbidden();

        $this->actingAs($this->administrador())
            ->patch(route('admin.usuarios.rol', $usuario), ['rol' => RolUsuario::Asesor->value])
            ->assertRedirect(route('admin.usuarios.index'));

        // Se relee al usuario de la base, como hace cada petición real
        $this->actingAs(User::find($usuario->id))->get(route('asesor.citas.index'))->assertOk();
    }

    public function test_hu_26_4_la_vista_de_permisos_lista_lo_que_hay_en_la_tabla_por_rol(): void
    {
        $asesor = Role::where('codigo', RolUsuario::Asesor)->firstOrFail();
        $cliente = Role::where('codigo', RolUsuario::Cliente)->firstOrFail();
        $asesor->update(['nombre' => 'Asesor de prueba']);
        $cliente->update(['nombre' => 'Cliente de prueba']);
        Permiso::create(['rol_id' => $asesor->id, 'modulo' => 'agendaunica', 'accion' => 'read', 'activo' => true]);
        Permiso::create(['rol_id' => $cliente->id, 'modulo' => 'favoritosunicos', 'accion' => 'create', 'activo' => false]);

        $this->actingAs($this->administrador())
            ->get(route('admin.permisos.index'))
            ->assertOk()
            ->assertSeeInOrder(['Asesor de prueba', 'agendaunica', 'Consultar', 'Activo', 'Cliente de prueba', 'favoritosunicos', 'Crear', 'Inactivo']);
    }
}
