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

/**
 * Reservas del cliente autenticado (HU-07 / HU-23).
 *
 * Modelo principal: {@see Reserva}, ligada a un {@see Inmueble}. La creación
 * y cancelación se delegan en {@see ReservaService}; el registro y pago del
 * depósito, en {@see PagoService} y {@see StripeCardService}.
 */
class ReservaController extends Controller
{
    public function __construct(
        private readonly ReservaService $reservas,
        private readonly PagoService $pagos,
        private readonly StripeCardService $tarjetas,
    ) {}

    /** Lista las reservas del cliente autenticado agrupadas por estado. */
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

    /** Crea una nueva reserva del cliente autenticado sobre un inmueble. */
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

    /** Detalle de una reserva propia: pagos, historial, contrato y tarjetas disponibles para pagar. */
    public function show(Reserva $reserva): View
    {
        $this->authorize('view', $reserva);

        return view('reservas.show', [
            'reserva' => $reserva->load(['inmueble', 'pagos.revisor', 'historial.autor', 'contrato']),
            'tarjetas' => $this->tarjetas->listar($reserva->cliente),
        ]);
    }

    /** Registra el pago declarado por el cliente para una reserva, a la espera de revisión (HU-23). */
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

    /** Paga el depósito de la reserva con una tarjeta ya guardada, sin volver a pedir datos. */
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

    /** Cancela una reserva propia antes de haber registrado el pago (HU-07.5). */
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
