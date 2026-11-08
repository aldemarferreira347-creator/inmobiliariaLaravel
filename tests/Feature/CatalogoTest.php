<?php

namespace Tests\Feature;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\TipoInmueble;
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
        // Títulos explícitos: los de la factory se repiten y harían el assertDontSee aleatorio
        $enNeiva = Inmueble::factory()->deVenta()->create(['ciudad' => 'Neiva', 'titulo' => 'Casa del centro de Neiva']);
        $enBogota = Inmueble::factory()->deVenta()->create(['ciudad' => 'Bogotá', 'titulo' => 'Apartamento en Chapinero']);

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

    public function test_hu_01_1_el_listado_muestra_los_datos_de_cada_inmueble_en_todos_los_estados(): void
    {
        $disponible = Inmueble::factory()->deVenta()->create(['precio_venta' => 350_000_000, 'habitaciones' => 4, 'banos' => 3, 'barrio' => 'Quirinal']);
        $reservado = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Reservado]);
        $ocupado = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Ocupado]);

        $this->get(route('inmuebles.index'))
            ->assertOk()
            ->assertSee($disponible->imagen_url, false)
            ->assertSee($disponible->precios[0]['valor'])
            ->assertSee('Quirinal, Neiva')
            ->assertSee('4 hab.')
            ->assertSee('3 baños')
            ->assertSee($reservado->titulo)
            ->assertSee($ocupado->titulo)
            ->assertSee('Reservado')
            ->assertSee('Ocupado');
    }

    public function test_hu_01_2_la_ficha_disponible_tiene_etiqueta_verde_y_boton_reservar(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Disponible]);

        $this->actingAs(User::factory()->create())
            ->get(route('inmuebles.show', $inmueble))
            ->assertOk()
            ->assertSee('badge-estado disponible', false)
            ->assertSee('>Disponible</span>', false)
            ->assertSeeInOrder(['btn-reservar-inmueble', 'Reservar'])
            ->assertDontSee('Reservar este inmueble')
            ->assertDontSee('btn-reservar-inmueble" disabled', false);
    }

    public function test_hu_01_3_la_ficha_reservada_muestra_el_boton_reservar_deshabilitado(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Reservado]);

        $this->actingAs(User::factory()->create())
            ->get(route('inmuebles.show', $inmueble))
            ->assertOk()
            ->assertSee('badge-estado reservado', false)
            ->assertSee('>Reservado</span>', false)
            ->assertSeeInOrder(['<button type="button" class="btn-reservar-inmueble"', 'disabled', 'Reservar']);
    }

    public function test_hu_01_4_la_ficha_ocupada_no_ofrece_reservar(): void
    {
        $inmueble = Inmueble::factory()->deVenta()->create(['estado' => EstadoInmueble::Ocupado]);

        $this->actingAs(User::factory()->create())
            ->get(route('inmuebles.show', $inmueble))
            ->assertOk()
            ->assertSee('badge-estado ocupado', false)
            ->assertDontSee('btn-reservar-inmueble', false)
            ->assertDontSee('Reservar');
    }

    public function test_hu_01_5_sin_inmuebles_se_muestra_el_aviso(): void
    {
        $this->get(route('inmuebles.index'))
            ->assertOk()
            ->assertSee('No hay inmuebles disponibles en este momento');
    }

    public function test_hu_02_1_el_filtro_por_tipo_muestra_solo_ese_tipo(): void
    {
        $apto = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Apartamento, 'titulo' => 'Apto unico filtro']);
        $casa = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Casa, 'titulo' => 'Casa unica filtro']);

        $this->get(route('inmuebles.index', ['tipo' => 'apartamento']))
            ->assertSee($apto->titulo)
            ->assertDontSee($casa->titulo);
    }

    public function test_hu_02_2_los_filtros_se_combinan(): void
    {
        $cumple = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Casa, 'precio_venta' => 300_000_000, 'habitaciones' => 3, 'titulo' => 'Casa que cumple']);
        $barata = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Casa, 'precio_venta' => 100_000_000, 'habitaciones' => 3, 'titulo' => 'Casa barata']);
        $pocas = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Casa, 'precio_venta' => 300_000_000, 'habitaciones' => 1, 'titulo' => 'Casa pocas hab']);
        $apto = Inmueble::factory()->deVenta()->create(['tipo' => TipoInmueble::Apartamento, 'precio_venta' => 300_000_000, 'habitaciones' => 3, 'titulo' => 'Apto de otro tipo']);

        $this->get(route('inmuebles.index', ['tipo' => 'casa', 'precio_min' => 200_000_000, 'precio_max' => 400_000_000, 'habitaciones' => 3]))
            ->assertSee($cumple->titulo)
            ->assertDontSee($barata->titulo)
            ->assertDontSee($pocas->titulo)
            ->assertDontSee($apto->titulo);
    }

    public function test_hu_02_3_limpiar_filtros_enlaza_al_listado_sin_query(): void
    {
        Inmueble::factory()->deVenta()->create();

        $this->get(route('inmuebles.index', ['tipo' => 'casa']))
            ->assertSee('Limpiar filtros')
            ->assertSee('href="'.route('inmuebles.index').'"', false);
    }

    public function test_hu_02_4_sin_resultados_ofrece_modificar_los_filtros(): void
    {
        $this->get(route('inmuebles.index', ['ubicacion' => 'Atlantida']))
            ->assertSee('No se encontraron inmuebles con los criterios seleccionados')
            ->assertSee('Modificar filtros');
    }

    public function test_hu_02_5_un_inmueble_inexistente_muestra_su_propio_404(): void
    {
        $this->get('/inmuebles/999999')
            ->assertNotFound()
            ->assertSee('Inmueble no encontrado')
            ->assertSee('href="'.route('inmuebles.index').'"', false);
    }

    public function test_hu_02_5_otras_rutas_inexistentes_conservan_el_404_generico(): void
    {
        $this->get('/ruta-que-no-existe')
            ->assertNotFound()
            ->assertSee('No encontramos lo que buscabas')
            ->assertDontSee('Inmueble no encontrado');
    }

    public function test_hu_09_3_el_estado_se_consulta_como_json(): void
    {
        $inmueble = Inmueble::factory()->create(['estado' => EstadoInmueble::Reservado]);

        $this->getJson(route('inmuebles.estado', $inmueble))
            ->assertOk()
            ->assertExactJson(['estado' => 'Reservado']);
    }

    public function test_hu_09_3_la_ficha_expone_la_url_y_el_estado_para_el_sondeo(): void
    {
        $inmueble = Inmueble::factory()->create();

        $this->get(route('inmuebles.show', $inmueble))
            ->assertSee('data-url-estado="'.route('inmuebles.estado', $inmueble).'"', false)
            ->assertSee('data-estado="Disponible"', false);
    }
}
