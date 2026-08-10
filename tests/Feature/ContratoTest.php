<?php

namespace Tests\Feature;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Models\Contrato;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoTest extends TestCase
{
    /** Reserva confirmada hace los días indicados, con su rastro en el historial */
    private function reservaConfirmada(int $haceDias = 0): Reserva
    {
        $inmueble = Inmueble::factory()->deArriendo()->create(['estado' => EstadoInmueble::Reservado]);
        $reserva = Reserva::factory()->confirmada()->create(['inmueble_id' => $inmueble->id]);

        HistorialReserva::create([
            'reserva_id' => $reserva->id,
            'estado_anterior' => EstadoReserva::PendientePago->value,
            'estado_nuevo' => EstadoReserva::Confirmada->value,
            'comentario' => 'Pago aprobado.',
        ])->forceFill(['creado_en' => now()->subDays($haceDias)])->save();

        return $reserva;
    }

    private function datos(Reserva $reserva): array
    {
        return [
            'reserva_id' => $reserva->id,
            'fecha_inicio' => now()->format('Y-m-d'),
            'fecha_fin' => now()->addYear()->format('Y-m-d'),
            'valor_mensual' => 1_500_000,
        ];
    }

    public function test_el_contrato_ocupa_el_inmueble(): void
    {
        $reserva = $this->reservaConfirmada();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.contratos.store'), $this->datos($reserva))
            ->assertSessionHasNoErrors();

        $contrato = Contrato::firstOrFail();

        $this->assertStringStartsWith('CON-', $contrato->numero_contrato);
        $this->assertSame(EstadoInmueble::Ocupado, $reserva->inmueble->refresh()->estado);
    }

    public function test_no_se_emite_contrato_desde_una_reserva_sin_confirmar(): void
    {
        $reserva = Reserva::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.contratos.store'), $this->datos($reserva))
            ->assertSessionHasErrors('reserva_id');
    }

    public function test_vencido_el_plazo_de_siete_dias_ya_no_se_emite_contrato(): void
    {
        $reserva = $this->reservaConfirmada(haceDias: Contrato::DIAS_PARA_EMITIR + 1);

        $this->actingAs(User::factory()->administrador()->create())
            ->post(route('admin.contratos.store'), $this->datos($reserva))
            ->assertSessionHasErrors('reserva_id');

        $this->assertSame(0, Contrato::count());
    }

    public function test_una_reserva_no_admite_dos_contratos(): void
    {
        $reserva = $this->reservaConfirmada();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.contratos.store'), $this->datos($reserva));
        $this->actingAs($admin)->post(route('admin.contratos.store'), $this->datos($reserva))
            ->assertSessionHasErrors('reserva_id');

        $this->assertSame(1, Contrato::count());
    }

    public function test_rescindir_libera_el_inmueble(): void
    {
        $reserva = $this->reservaConfirmada();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.contratos.store'), $this->datos($reserva));
        $contrato = Contrato::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.contratos.rescindir', $contrato), ['motivo' => 'Acuerdo entre las partes.'])
            ->assertRedirect(route('admin.contratos.index'));

        $this->assertSame(EstadoContrato::Rescindido, $contrato->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $reserva->inmueble->refresh()->estado);
    }

    public function test_el_comando_vence_los_contratos_caducados_y_libera_el_inmueble(): void
    {
        $reserva = $this->reservaConfirmada();
        $reserva->inmueble->update(['estado' => EstadoInmueble::Ocupado]);

        $contrato = Contrato::factory()->vencido()->create(['reserva_id' => $reserva->id]);

        $this->artisan('contratos:vencer')->assertSuccessful();

        $this->assertSame(EstadoContrato::Vencido, $contrato->refresh()->estado);
        $this->assertSame(EstadoInmueble::Disponible, $reserva->inmueble->refresh()->estado);
    }

    public function test_el_pdf_firmado_se_guarda_fuera_de_la_carpeta_publica(): void
    {
        Storage::fake('local');

        $reserva = $this->reservaConfirmada();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.contratos.store'), $this->datos($reserva));
        $contrato = Contrato::firstOrFail();

        $this->actingAs($admin)->post(route('admin.contratos.documento', $contrato), [
            'documento' => UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists($contrato->refresh()->archivo_ruta);
    }

    public function test_solo_el_dueno_y_el_administrador_descargan_el_contrato(): void
    {
        Storage::fake('local');

        $reserva = $this->reservaConfirmada();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('admin.contratos.store'), $this->datos($reserva));
        $contrato = Contrato::firstOrFail();

        $this->actingAs($admin)->post(route('admin.contratos.documento', $contrato), [
            'documento' => UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf'),
        ]);

        $this->actingAs($reserva->cliente)->get(route('contratos.descargar', $contrato))->assertOk();
        $this->actingAs($admin)->get(route('contratos.descargar', $contrato))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('contratos.descargar', $contrato))->assertForbidden();
    }
}
