<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarFranjaRequest;
use App\Models\ConfigFranjaCita;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configuración de las franjas horarias disponibles para agendar citas (RF-26.2).
 *
 * Modelo principal: {@see ConfigFranjaCita}, una fila por día de la semana.
 */
class FranjaController extends Controller
{
    /** Muestra la configuración de franja horaria de cada día de la semana. */
    public function index(): View
    {
        return view('admin.franjas.index', [
            'franjas' => ConfigFranjaCita::all()->keyBy('dia_semana'),
            'dias' => ConfigFranjaCita::DIAS_SEMANA,
        ]);
    }

    /** Guarda el horario y el estado (activo/inactivo) de la franja de un día. */
    public function update(ActualizarFranjaRequest $request): RedirectResponse
    {
        ConfigFranjaCita::guardarPara(
            $request->string('dia_semana')->toString(),
            $request->string('hora_inicio')->toString().':00',
            $request->string('hora_fin')->toString().':00',
            $request->integer('intervalo_minutos'),
            $request->estaActiva(),
            $request->user(),
        );

        return redirect()
            ->route('admin.franjas.index')
            ->with(['mensaje' => "Franja de {$request->input('dia_semana')} actualizada correctamente.", 'tipo' => 'success']);
    }
}
