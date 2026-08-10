<?php

namespace Tests\Unit;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\EstadoVenta;
use App\Models\Contrato;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\Venta;
use Tests\TestCase;

/**
 * HU-09 / HU-14 / HU-17: el estado del inmueble se deriva de sus reservas,
 * contratos y ventas. Reservar y ocupar son cosas distintas: una reserva
 * confirmada reserva el inmueble, y solo el contrato lo ocupa.
 */
class EstadoInmuebleTest extends TestCase
{
    private function inmuebleCon(EstadoReserva $estado): Inmueble
    {
        $inmueble = Inmueble::factory()->create();
        Reserva::factory()->create(['inmueble_id' => $inmueble->id, 'estado' => $estado]);

        return $inmueble;
    }

    public function test_sin_operaciones_el_inmueble_esta_disponible(): void
    {
        $this->assertSame(EstadoInmueble::Disponible, Inmueble::factory()->create()->estadoCalculado());
    }

    public function test_una_reserva_pendiente_de_pago_lo_marca_reservado(): void
    {
        $inmueble = $this->inmuebleCon(EstadoReserva::PendientePago);

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->estadoCalculado());
    }

    public function test_una_reserva_rechazada_sigue_bloqueando_el_inmueble(): void
    {
        $inmueble = $this->inmuebleCon(EstadoReserva::Rechazada);

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->estadoCalculado());
    }

    public function test_una_reserva_confirmada_lo_reserva_pero_no_lo_ocupa(): void
    {
        $inmueble = $this->inmuebleCon(EstadoReserva::Confirmada);

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->estadoCalculado());
    }

    public function test_un_contrato_vigente_lo_marca_ocupado(): void
    {
        $inmueble = Inmueble::factory()->create();
        $reserva = Reserva::factory()->confirmada()->create(['inmueble_id' => $inmueble->id]);

        Contrato::factory()->create(['reserva_id' => $reserva->id]);

        $this->assertSame(EstadoInmueble::Ocupado, $inmueble->estadoCalculado());
    }

    public function test_una_reserva_cancelada_no_bloquea_el_inmueble(): void
    {
        $inmueble = $this->inmuebleCon(EstadoReserva::Cancelada);

        $this->assertSame(EstadoInmueble::Disponible, $inmueble->estadoCalculado());
    }

    public function test_una_venta_en_proceso_lo_reserva_y_una_cerrada_lo_ocupa(): void
    {
        $inmueble = Inmueble::factory()->create();
        $venta = Venta::factory()->create(['inmueble_id' => $inmueble->id]);

        $this->assertSame(EstadoInmueble::Reservado, $inmueble->estadoCalculado());

        $venta->update(['estado' => EstadoVenta::Cerrada]);

        $this->assertSame(EstadoInmueble::Ocupado, $inmueble->fresh()->estadoCalculado());
    }
}
