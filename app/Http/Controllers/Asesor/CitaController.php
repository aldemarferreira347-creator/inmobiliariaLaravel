<?php

namespace App\Http\Controllers\Asesor;

use App\Enumerados\EstadoCita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asesor\RegistrarObservacionRequest;
use App\Models\Cita;
use App\Servicios\CitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Citas asignadas al asesor autenticado (HU-11 / HU-12).
 *
 * Modelo principal: {@see Cita}. Marcar la visita como realizada o editar
 * su observación se delega en {@see CitaService}.
 */
class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

    /** Lista las citas asignadas al asesor autenticado, con filtro opcional por estado. */
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoCita::class)],
        ]);

        $citas = $request->user()
            ->citasAsignadas()
            ->with('cliente', 'inmueble', 'observacion')
            ->when(filled($filtros['estado'] ?? null), fn ($q) => $q->where('estado', $filtros['estado']))
            ->orderBy('fecha')
            ->get();

        return view('asesor.citas.index', [
            'citas' => $citas,
            'filtroEstado' => $filtros['estado'] ?? null,
        ]);
    }

    /** Detalle de una cita asignada al asesor, con su historial de auditoría. */
    public function show(Cita $cita): View
    {
        $this->authorize('view', $cita);

        return view('asesor.citas.show', [
            'cita' => $cita->load('cliente', 'inmueble', 'observacion', 'historial.usuario'),
        ]);
    }

    /** Marca la visita como realizada y registra la observación del asesor. */
    public function registrarObservacion(RegistrarObservacionRequest $request, Cita $cita): RedirectResponse
    {
        $this->citas->marcarRealizada($cita, $request->user(), $request->string('observaciones')->toString());

        return redirect()
            ->route('asesor.citas.show', $cita)
            ->with(['mensaje' => 'Visita registrada correctamente.', 'tipo' => 'success']);
    }

    /** Edita la observación ya registrada de una visita realizada. */
    public function editarObservacion(RegistrarObservacionRequest $request, Cita $cita): RedirectResponse
    {
        $this->citas->editarObservacion($cita, $request->user(), $request->string('observaciones')->toString());

        return redirect()
            ->route('asesor.citas.show', $cita)
            ->with(['mensaje' => 'Observación actualizada correctamente.', 'tipo' => 'success']);
    }
}
