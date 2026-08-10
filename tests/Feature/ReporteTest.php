<?php

namespace Tests\Feature;

use App\Enumerados\EstadoReserva;
use App\Enumerados\TipoReporte;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_un_cliente_no_accede_a_los_reportes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.reportes.index'))
            ->assertForbidden();
    }

    public function test_el_panel_de_reportes_muestra_los_cuatro_tipos(): void
    {
        $respuesta = $this->actingAs($this->administrador())->get(route('admin.reportes.index'))->assertOk();

        foreach (TipoReporte::cases() as $tipo) {
            $respuesta->assertSee($tipo->etiqueta());
        }
    }

    public function test_el_reporte_de_reservaciones_lista_las_del_periodo(): void
    {
        $reserva = Reserva::factory()->create();

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', TipoReporte::Reservaciones->value))
            ->assertOk()
            ->assertSee($reserva->codigo_reserva);
    }

    public function test_el_filtro_de_estado_acota_el_resultado(): void
    {
        $confirmada = Reserva::factory()->confirmada()->create();
        $pendiente = Reserva::factory()->create();

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', [
                TipoReporte::Reservaciones->value,
                'estado' => EstadoReserva::Confirmada->value,
            ]))
            ->assertOk()
            ->assertSee($confirmada->codigo_reserva)
            ->assertDontSee($pendiente->codigo_reserva);
    }

    public function test_el_rango_de_fechas_deja_fuera_lo_anterior(): void
    {
        $antigua = Reserva::factory()->create();
        $antigua->forceFill(['created_at' => now()->subMonths(6)])->save();

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', [
                TipoReporte::Reservaciones->value,
                'desde' => now()->subDays(7)->format('Y-m-d'),
                'hasta' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertDontSee($antigua->codigo_reserva);
    }

    public function test_sin_datos_se_informa_al_administrador(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', TipoReporte::Ventas->value))
            ->assertOk()
            ->assertSee('No hay registros disponibles');
    }

    public function test_cada_tipo_se_exporta_a_excel(): void
    {
        Reserva::factory()->create();
        Pago::factory()->create();
        Contrato::factory()->create();
        Venta::factory()->create();

        foreach (TipoReporte::cases() as $tipo) {
            $respuesta = $this->actingAs($this->administrador())
                ->get(route('admin.reportes.excel', $tipo->value))
                ->assertOk();

            $this->assertSame(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $respuesta->headers->get('content-type'),
            );
        }
    }

    public function test_cada_tipo_se_exporta_a_pdf(): void
    {
        Reserva::factory()->create();
        Pago::factory()->create();
        Contrato::factory()->create();
        Venta::factory()->create();

        foreach (TipoReporte::cases() as $tipo) {
            $respuesta = $this->actingAs($this->administrador())
                ->get(route('admin.reportes.pdf', $tipo->value))
                ->assertOk();

            $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type'));
        }
    }

    public function test_un_tipo_de_reporte_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', 'inventado'))
            ->assertNotFound();
    }
}
