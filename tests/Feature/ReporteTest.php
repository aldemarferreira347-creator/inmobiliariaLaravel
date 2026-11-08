<?php

namespace Tests\Feature;

use App\Enumerados\EstadoReserva;
use App\Enumerados\PeriodoReporte;
use App\Enumerados\TipoReporte;
use App\Models\Contrato;
use App\Models\Inmueble;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Servicios\Reportes\FabricaReportes;
use App\Servicios\Reportes\FiltroReporte;
use App\Servicios\Reportes\Reporte;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    private function administrador(): User
    {
        return User::factory()->administrador()->create();
    }

    public function test_el_excel_no_interpreta_formulas_de_los_datos(): void
    {
        $inmueble = Inmueble::factory()->create(['titulo' => '=1+1']);
        Reserva::factory()->create(['inmueble_id' => $inmueble->id]);

        $contenido = $this->actingAs($this->administrador())
            ->get(route('admin.reportes.excel', TipoReporte::Reservaciones->value))
            ->assertOk()
            ->streamedContent();

        $archivo = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($archivo, $contenido);
        $celda = IOFactory::load($archivo)->getActiveSheet()->getCell('C5');
        unlink($archivo);

        $this->assertSame(DataType::TYPE_STRING, $celda->getDataType());
        $this->assertSame('=1+1', $celda->getValue());
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

    public function test_hu_06_1_reporte_diario_con_fecha_inmueble_cliente_y_estado(): void
    {
        $reserva = Reserva::factory()->confirmada()->create();
        $hoy = now()->format('Y-m-d');

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', [TipoReporte::Reservaciones->value, 'desde' => $hoy, 'hasta' => $hoy]))
            ->assertOk()
            ->assertSeeInOrder(['Fecha', 'Inmueble', 'Cliente', 'Estado'])
            ->assertSee($reserva->created_at->format('d/m/Y'))
            ->assertSee($reserva->inmueble->titulo)
            ->assertSee($reserva->cliente->nombre)
            ->assertSee(EstadoReserva::Confirmada->etiqueta());
    }

    public function test_hu_06_3_error_al_construir_el_reporte_ofrece_intentar_nuevamente(): void
    {
        $this->mock(FabricaReportes::class)
            ->shouldReceive('crear')
            ->andThrow(new \RuntimeException('Base de datos no disponible'));

        $url = route('admin.reportes.show', [TipoReporte::Reservaciones->value, 'estado' => 'Confirmada']);

        $respuesta = $this->actingAs($this->administrador())
            ->get($url)
            ->assertOk()
            ->assertSee('No se pudo generar el reporte')
            ->assertSee('Intentar nuevamente');

        $this->assertStringContainsString(
            'href="'.e($url).'"',
            $respuesta->getContent(),
            'El botón repite la misma petición con los mismos filtros.'
        );
    }

    public function test_hu_21_1_reporte_de_pagos_con_monto_metodo_pasarela_y_estado(): void
    {
        $pago = Pago::factory()->pagado()->create([
            'metodo_pago' => 'tarjeta_credito',
            'monto' => 1_500_000,
            'referencia_pasarela' => 'pi_123',
        ]);

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', TipoReporte::Pagos->value))
            ->assertOk()
            ->assertSeeInOrder(['Monto', 'Método', 'Pasarela', 'Estado'])
            ->assertSee('1.500.000')
            ->assertSee($pago->metodo_pago->etiqueta())
            ->assertSee('Stripe')
            ->assertSee($pago->estado->etiqueta());
    }

    public function test_hu_21_2_contratos_vigentes_con_numero_inmueble_cliente_valor_y_fin(): void
    {
        $vigente = Contrato::factory()->create();
        $vencido = Contrato::factory()->create(['estado' => 'Vencido']);

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', [TipoReporte::Contratos->value, 'estado' => 'Vigente']))
            ->assertOk()
            ->assertSeeInOrder(['contrato', 'Inmueble', 'Cliente', 'Valor mensual', 'Fin'])
            ->assertSee($vigente->numero_contrato)
            ->assertSee($vigente->reserva->inmueble->titulo)
            ->assertSee($vigente->reserva->cliente->nombre)
            ->assertSee(number_format((float) $vigente->valor_mensual, 0, ',', '.'))
            ->assertSee($vigente->fecha_fin->format('d/m/Y'))
            ->assertDontSee($vencido->numero_contrato);
    }

    public function test_hu_21_3_ventas_por_rango_con_precio_total_y_asesor(): void
    {
        $venta = Venta::factory()->create(['fecha_venta' => now()->subDay()]);
        $fuera = Venta::factory()->create();
        $fuera->forceFill(['fecha_venta' => now()->subYear()])->save();

        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', [
                TipoReporte::Ventas->value,
                'desde' => now()->subDays(7)->format('Y-m-d'),
                'hasta' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSeeInOrder(['Asesor', 'Precio'])
            ->assertSee(number_format((float) $venta->precio_venta, 0, ',', '.'))
            ->assertSee($venta->asesor->nombre)
            ->assertDontSee($fuera->inmueble->titulo);
    }

    public function test_la_exportacion_limita_el_rango_a_31_dias_en_el_diario_y_12_meses_en_el_resto(): void
    {
        $admin = $this->administrador();
        $hasta = now()->format('Y-m-d');

        foreach (['excel', 'pdf'] as $formato) {
            $this->actingAs($admin)
                ->from(route('admin.reportes.show', 'reservaciones'))
                ->get(route("admin.reportes.$formato", ['reservaciones', 'desde' => now()->subDays(40)->format('Y-m-d'), 'hasta' => $hasta]))
                ->assertRedirect(route('admin.reportes.show', 'reservaciones'))
                ->assertSessionHas('mensaje', 'El rango a exportar no puede superar 31 días. Acorte las fechas e intente de nuevo.');

            $this->actingAs($admin)
                ->get(route("admin.reportes.$formato", ['pagos', 'desde' => now()->subMonths(13)->format('Y-m-d'), 'hasta' => $hasta]))
                ->assertSessionHas('mensaje', 'El rango a exportar no puede superar 12 meses. Acorte las fechas e intente de nuevo.');

            $this->actingAs($admin)
                ->get(route("admin.reportes.$formato", ['pagos', 'desde' => now()->subMonths(11)->format('Y-m-d'), 'hasta' => $hasta]))
                ->assertOk();
        }
    }

    public function test_hu_06_1_el_reporte_de_reservaciones_ofrece_generar_reporte_diario_de_una_fecha(): void
    {
        $hoy = Reserva::factory()->create();
        $ayer = Reserva::factory()->create();
        $ayer->forceFill(['created_at' => now()->subDay()])->save();
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->get(route('admin.reportes.show', TipoReporte::Reservaciones->value))
            ->assertSee('Generar reporte diario')
            ->assertSee('name="fecha"', false);

        $this->actingAs($admin)
            ->get(route('admin.reportes.show', [TipoReporte::Reservaciones->value, 'fecha' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee($hoy->codigo_reserva)
            ->assertDontSee($ayer->codigo_reserva);
    }

    /** Reporte sintético con N filas baratas, para probar los topes sin sembrar la base. */
    private function reporteConFilas(int $filas, bool $soloTotal = false): Reporte
    {
        $filtro = new FiltroReporte(PeriodoReporte::porDefecto(), null, null, null);

        return new class($filtro, $filas, $soloTotal) extends Reporte
        {
            public function __construct(FiltroReporte $filtro, private int $n, private bool $soloTotal)
            {
                parent::__construct($filtro);
            }

            public function tipo(): TipoReporte
            {
                return TipoReporte::Pagos;
            }

            public function columnas(): array
            {
                return ['A', 'B'];
            }

            public function filas(): Collection
            {
                return collect(range(1, $this->n))->map(fn ($i) => ["f{$i}", $i]);
            }

            public function total(): int
            {
                return $this->n;
            }

            public function resumen(): array
            {
                return [];
            }
        };
    }

    private function fabricaDevuelve(Reporte $reporte): void
    {
        $fabrica = \Mockery::mock(FabricaReportes::class);
        $fabrica->shouldReceive('crear')->andReturn($reporte);
        $this->app->instance(FabricaReportes::class, $fabrica);
    }

    public function test_el_pdf_con_mas_de_200_filas_se_rechaza(): void
    {
        $origen = route('admin.reportes.show', 'pagos');

        $this->fabricaDevuelve($this->reporteConFilas(201));
        $this->actingAs($this->administrador())->from($origen)
            ->get(route('admin.reportes.pdf', 'pagos'))
            ->assertRedirect($origen)
            ->assertSessionHas('mensaje', 'El reporte tiene 201 filas y el PDF admite hasta 200. Acorte el rango o exporte a Excel.');
    }

    public function test_el_pdf_con_200_filas_se_genera(): void
    {
        $this->fabricaDevuelve($this->reporteConFilas(200));

        $respuesta = $this->actingAs($this->administrador())->get(route('admin.reportes.pdf', 'pagos'))->assertOk();

        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type'));
    }

    public function test_el_excel_con_mas_de_5000_filas_se_rechaza(): void
    {
        $origen = route('admin.reportes.show', 'pagos');

        $this->fabricaDevuelve($this->reporteConFilas(5001));
        $this->actingAs($this->administrador())->from($origen)
            ->get(route('admin.reportes.excel', 'pagos'))
            ->assertRedirect($origen)
            ->assertSessionHas('mensaje', 'El reporte tiene 5001 filas y el Excel admite hasta 5.000. Acorte el rango.');
    }

    public function test_el_excel_con_5000_filas_se_genera(): void
    {
        $this->fabricaDevuelve($this->reporteConFilas(5000));

        $this->actingAs($this->administrador())->get(route('admin.reportes.excel', 'pagos'))->assertOk();
    }

    public static function rutasConFabrica(): array
    {
        return ['excel' => ['admin.reportes.excel'], 'pdf' => ['admin.reportes.pdf']];
    }

    #[DataProvider('rutasConFabrica')]
    public function test_hu_06_3_error_en_exportaciones_ofrece_intentar_nuevamente(string $ruta): void
    {
        $this->mock(FabricaReportes::class)
            ->shouldReceive('crear')
            ->andThrow(new \RuntimeException('Base de datos no disponible'));
        $url = route($ruta, ['pagos', 'estado' => 'Pagado']);

        $respuesta = $this->actingAs($this->administrador())->get($url)
            ->assertOk()
            ->assertSee('No se pudo generar el reporte')
            ->assertSee('Intentar nuevamente');
        $this->assertStringContainsString('href="'.e($url).'"', $respuesta->getContent());
    }

    public function test_hu_06_3_error_en_el_indice_ofrece_intentar_nuevamente(): void
    {
        $this->mock(FabricaReportes::class)
            ->shouldReceive('crear')
            ->andThrow(new \RuntimeException('Base de datos no disponible'));

        $this->actingAs($this->administrador())->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee('Intentar nuevamente');
    }

    public function test_un_tipo_de_reporte_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('admin.reportes.show', 'inventado'))
            ->assertNotFound();
    }
}
