<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitarCitaRequest;
use App\Models\Cita;
use App\Models\ConfigFranjaCita;
use App\Models\Inmueble;
use App\Servicios\CitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Citas de visita del cliente autenticado (HU-27).
 *
 * Modelo principal: {@see \App\Models\Cita}, agendada sobre un
 * {@see \App\Models\Inmueble} y validada contra los huecos definidos en
 * {@see \App\Models\ConfigFranjaCita}. La lógica de negocio vive en
 * {@see CitaService}, no en este controlador.
 */
class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

    /** Lista las citas del cliente autenticado con el inmueble y el asesor asignado. */
    public function index(Request $request): View
    {
        $citas = $request->user()
            ->citas()
            ->with('inmueble', 'asesor')
            ->orderByDesc('fecha')
            ->get();

        return view('citas.index', [
            'usuario' => $request->user(),
            'citas' => $citas,
        ]);
    }

    /** Registra la solicitud de visita de un cliente para un inmueble. */
    public function store(SolicitarCitaRequest $request): RedirectResponse
    {
        $inmueble = Inmueble::findOrFail($request->integer('inmueble_id'));

        $cita = $this->citas->solicitar($inmueble, $request->user(), $request->fechaHora());

        return redirect()
            ->route('inmuebles.show', $inmueble)
            ->with([
                'mensaje' => "Visita solicitada para el {$cita->fecha->format('d/m/Y')} a las {$cita->fecha->format('H:i')}. "
                    .'Un asesor te contactará para confirmarla.',
                'tipo' => 'success',
            ]);
    }

    /** Cancela una cita propia del cliente autenticado. */
    public function cancelar(Request $request, Cita $cita): RedirectResponse
    {
        $this->authorize('cancelar', $cita);

        $this->citas->cancelar($cita, $request->user());

        return redirect()->route('citas.index')->with(['mensaje' => 'Cita cancelada correctamente.', 'tipo' => 'success']);
    }

    /** Devuelve en JSON las horas realmente libres para el selector del formulario (HU-27.1/27.2). */
    // HU-27.1/27.2: horas realmente disponibles para el selector del formulario
    public function franjasDisponibles(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'inmueble_id' => ['required', 'integer', 'exists:inmueble,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'horas' => ConfigFranjaCita::disponiblesPara((int) $datos['inmueble_id'], $datos['fecha']),
        ]);
    }
}
