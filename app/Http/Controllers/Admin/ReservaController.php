<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\EstadoReserva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevisarPagoRequest;
use App\Models\Pago;
use App\Models\Reserva;
use App\Servicios\PagoService;
use App\Servicios\ReservaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Seguimiento y revisión de reservas desde el panel (HU-07 / HU-23).
 *
 * Modelo principal: {@see Reserva}, con sus {@see Pago} asociados. La
 * revisión (aprobar/rechazar pago) y la cancelación se delegan en
 * {@see PagoService} y {@see ReservaService} respectivamente.
 */
class ReservaController extends Controller
{
    public function __construct(
        private readonly ReservaService $reservas,
        private readonly PagoService $pagos,
    ) {}

    /** Lista las reservas del sistema, con filtros por estado y rango de fechas. */
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoReserva::class)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $reservas = Reserva::query()
            ->with(['inmueble', 'cliente'])
            ->when(filled($filtros['estado'] ?? null), fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(filled($filtros['desde'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $filtros['desde']))
            ->when(filled($filtros['hasta'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $filtros['hasta']))
            ->recientes()
            ->get();

        return view('admin.reservas.index', [
            'reservas' => $reservas,
            'filtros' => array_filter($filtros),
            'totalesPorEstado' => Reserva::query()
                ->selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado'),
        ]);
    }

    /** Detalle de una reserva: pagos, historial de auditoría y contrato asociado. */
    public function show(Reserva $reserva): View
    {
        return view('admin.reservas.show', [
            'reserva' => $reserva->load(['inmueble', 'cliente', 'pagos.revisor', 'historial.autor', 'contrato']),
            'pagoEnRevision' => $reserva->pagoEnRevision(),
        ]);
    }

    /** Aprueba o rechaza el pago que el cliente declaró para la reserva. */
    // Aprobación o rechazo del pago declarado por el cliente
    public function revisarPago(RevisarPagoRequest $request, Reserva $reserva, Pago $pago): RedirectResponse
    {
        abort_unless($pago->reserva_id === $reserva->id, 404);

        $request->apruebaElPago()
            ? $this->pagos->aprobar($pago, $request->user())
            : $this->pagos->rechazar($pago, $request->user(), $request->string('motivo_rechazo')->toString());

        return back()->with([
            'mensaje' => $request->apruebaElPago()
                ? "Pago aprobado. La reserva {$reserva->codigo_reserva} quedó confirmada."
                : 'Pago rechazado. El cliente podrá registrar otro intento.',
            'tipo' => 'success',
        ]);
    }

    /** Cancela una reserva desde el panel, dejando constancia del motivo. */
    public function cancelar(Request $request, Reserva $reserva): RedirectResponse
    {
        $this->authorize('cancelar', $reserva);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        $this->reservas->cancelar($reserva, $request->user(), $datos['motivo']);

        return redirect()
            ->route('admin.reservas.index')
            ->with(['mensaje' => "Reserva {$reserva->codigo_reserva} cancelada.", 'tipo' => 'success']);
    }
}
