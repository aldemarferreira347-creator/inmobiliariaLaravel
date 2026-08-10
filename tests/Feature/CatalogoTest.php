<?php

namespace Tests\Feature;

use App\Models\Inmueble;
use App\Models\User;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    public function test_el_inicio_se_muestra_a_cualquier_visitante(): void
    {
        Inmueble::factory()->count(3)->create();

        $this->get(route('inicio'))->assertOk();
    }

    public function test_el_catalogo_lista_los_inmuebles(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create();

        $this->get(route('inmuebles.index'))
            ->assertOk()
            ->assertSee($inmueble->titulo);
    }

    public function test_los_filtros_acotan_el_resultado(): void
    {
        $enNeiva = Inmueble::factory()->deVenta()->create(['ciudad' => 'Neiva']);
        $enBogota = Inmueble::factory()->deVenta()->create(['ciudad' => 'Bogotá']);

        $this->get(route('inmuebles.index', ['ubicacion' => 'Neiva']))
            ->assertOk()
            ->assertSee($enNeiva->titulo)
            ->assertDontSee($enBogota->titulo);
    }

    public function test_un_inmueble_inexistente_devuelve_404(): void
    {
        $this->get(route('inmuebles.show', 999))->assertNotFound();
    }

    public function test_el_detalle_de_un_inmueble_se_muestra(): void
    {
        $inmueble = Inmueble::factory()->deArriendo()->create();

        $this->get(route('inmuebles.show', $inmueble))
            ->assertOk()
            ->assertSee($inmueble->codigo);
    }

    public function test_los_favoritos_se_marcan_y_se_retiran(): void
    {
        $usuario = User::factory()->create();
        $inmueble = Inmueble::factory()->create();

        $this->actingAs($usuario)->post(route('favoritos.toggle', $inmueble));
        $this->assertTrue($inmueble->esFavoritoDe($usuario));

        $this->post(route('favoritos.toggle', $inmueble));
        $this->assertFalse($inmueble->esFavoritoDe($usuario));
    }

    public function test_los_favoritos_exigen_sesion_iniciada(): void
    {
        $inmueble = Inmueble::factory()->create();

        $this->post(route('favoritos.toggle', $inmueble))->assertRedirect(route('login'));
    }
}
