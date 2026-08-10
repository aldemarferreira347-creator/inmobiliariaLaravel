<?php

namespace App\Http\Controllers\Admin;

use App\Enumerados\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AsignarCitaRequest;
use App\Models\Cita;
use App\Models\User;
use App\Servicios\CitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// HU-10: gestión de citas desde el panel de administración
class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

    public function index(): View
    {
        $sinAsignar = Cita::query()
            ->sinAsignar()
            ->with('cliente', 'inmueble')
            ->orderBy('fecha')
            ->get();

        $porAsesor = Cita::query()
            ->whereNotNull('asesor_id')
            ->with('cliente', 'inmueble', 'asesor', 'observacion')
            ->orderBy('fecha')
            ->get()
            ->groupBy('asesor_id');

        return view('admin.citas.index', [
            'sinAsignar' => $sinAsignar,
            'porAsesor' => $porAsesor,
            'asesoresDisponibles' => User::delRol(RolUsuario::Asesor)->activos()->orderBy('nombre')->get(),
        ]);
    }

    public function asignar(AsignarCitaRequest $request, Cita $cita): RedirectResponse
    {
        $asesor = User::findOrFail($request->integer('asesor_id'));

        $this->citas->asignar($cita, $asesor, $request->user());

        return back()->with(['mensaje' => 'Asesor asignado correctamente.', 'tipo' => 'success']);
    }

    // Detalle de cita de solo lectura: cliente, inmueble, observación y auditoría
    public function show(Cita $cita): View
    {
        return view('admin.citas.show', [
            'cita' => $cita->load('cliente', 'inmueble', 'asesor', 'observacion', 'historial.usuario'),
        ]);
    }
}
