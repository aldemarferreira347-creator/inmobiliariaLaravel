<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarPagoRequest;
use App\Http\Requests\SolicitarReservaRequest;
use App\Models\Inmueble;
use App\Models\MetodoPagoGuardado;
use App\Models\Reserva;
use App\Servicios\PagoService;
use App\Servicios\ReservaService;
use App\Servicios\StripeCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-07 / HU-23: reservas del cliente autenticado
class ReservaController extends Controller
{
    public function __construct(
        private readonly ReservaService $reservas,
        private readonly PagoService $pagos,
        private readonly StripeCardService $tarjetas,
    ) {}

    public function index(Request $request): View
    {
        $reservas = $request->user()
            ->reservas()
            ->with('inmueble')
            ->recientes()
            ->get();

        return view('reservas.index', [
            'reservas' => $reservas,
            'totalesPorEstado' => $reservas->countBy(fn (Reserva $reserva) => $reserva->estado->value),
        ]);
    }

    public function store(SolicitarReservaRequest $request): RedirectResponse
    {
        $inmueble = Inmueble::findOrFail($request->integer('inmueble_id'));

        $reserva = $this->reservas->solicitar(
            $inmueble,
            $request->user(),
            $request->modalidad(),
            $request->input('notas_cliente'),
        );

        return redirect()
            ->route('reservas.show', $reserva)
            ->with(['mensaje' => "Reserva {$reserva->codigo_reserva} registrada correctamente.", 'tipo' => 'success']);
    }

    public function show(Reserva $reserva): View
    {
        $this->authorize('view', $reserva);

        return view('reservas.show', [
            'reserva' => $reserva->load(['inmueble', 'pagos.revisor', 'historial.autor', 'contrato']),
            'tarjetas' => $this->tarjetas->listar($reserva->cliente),
        ]);
    }

    // HU-23: el cliente declara el pago; queda a la espera de revisión
    public function registrarPago(RegistrarPagoRequest $request, Reserva $reserva): RedirectResponse
    {
        $this->pagos->registrar($reserva, $request->metodo(), $request->input('referencia'));

        return redirect()
            ->route('reservas.show', $reserva)
            ->with([
                'mensaje' => 'Pago registrado. Lo revisaremos y te avisaremos en cuanto quede confirmado.',
                'tipo' => 'success',
            ]);
    }

    // HU-20.4 / HU-23.1: paga la reserva con una tarjeta ya guardada, sin volver a pedir datos
    public function pagarConTarjeta(Request $request, Reserva $reserva, MetodoPagoGuardado $tarjeta): RedirectResponse
    {
        $this->authorize('registrarPago', $reserva);
        abort_unless($tarjeta->cliente_id === $request->user()->id, 404);

        $resultado = $this->tarjetas->pagarConTarjeta($reserva, $tarjeta);

        return redirect()
            ->route('reservas.show', $reserva)
            ->with(match ($resultado['estado']) {
                'succeeded' => ['mensaje' => "Pago aprobado. La reserva {$reserva->codigo_reserva} quedó confirmada.", 'tipo' => 'success'],
                default => ['mensaje' => 'Tu banco requiere una verificación adicional para completar el pago. Te avisaremos en cuanto se confirme.', 'tipo' => 'info'],
            });
    }

    // HU-07.5: el cliente retira su reserva antes de registrar el pago
    public function cancelar(Request $request, Reserva $reserva): RedirectResponse
    {
        $this->authorize('cancelar', $reserva);

        $this->reservas->cancelar($reserva, $request->user(), 'Cancelada por el cliente.');

        return redirect()
            ->route('reservas.index')
            ->with(['mensaje' => "Reserva {$reserva->codigo_reserva} cancelada.", 'tipo' => 'success']);
    }
}
