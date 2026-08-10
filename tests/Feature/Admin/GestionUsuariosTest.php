<?php

namespace Tests\Feature\Admin;

use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use App\Models\Auditoria;
use App\Models\User;
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
}
