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

// HU-27: citas de visita del cliente autenticado
class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

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

    public function cancelar(Request $request, Cita $cita): RedirectResponse
    {
        $this->authorize('cancelar', $cita);

        $this->citas->cancelar($cita, $request->user());

        return redirect()->route('citas.index')->with(['mensaje' => 'Cita cancelada correctamente.', 'tipo' => 'success']);
    }

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
