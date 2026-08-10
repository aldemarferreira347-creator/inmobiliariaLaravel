<?php

namespace App\Http\Controllers\Asesor;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asesor\StoreVentaRequest;
use App\Models\Inmueble;
use App\Models\User;
use App\Models\Venta;
use App\Servicios\VentaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-14: ventas gestionadas por el asesor
class VentaController extends Controller
{
    public function __construct(private readonly VentaService $ventas) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Venta::class);

        // El administrador supervisa todas; el asesor solo las suyas
        $ventas = Venta::query()
            ->with('inmueble', 'cliente', 'asesor')
            ->when(! $request->user()->esAdministrador(), fn ($q) => $q->where('asesor_id', $request->user()->id))
            ->latest('fecha_venta')
            ->get();

        return view('asesor.ventas.index', [
            'ventas' => $ventas,
            'inmueblesDisponibles' => Inmueble::where('estado', EstadoInmueble::Disponible)->orderBy('titulo')->get(),
            'clientes' => User::delRol(RolUsuario::Cliente)->activos()->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreVentaRequest $request): RedirectResponse
    {
        $venta = $this->ventas->registrar($request->validated(), $request->user());

        return redirect()
            ->route('asesor.ventas.index')
            ->with(['mensaje' => "Venta de «{$venta->inmueble->titulo}» registrada.", 'tipo' => 'success']);
    }

    public function show(Venta $venta): View
    {
        $this->authorize('view', $venta);

        return view('asesor.ventas.show', [
            'venta' => $venta->load('inmueble', 'cliente', 'asesor'),
        ]);
    }

    public function cerrar(Venta $venta): RedirectResponse
    {
        $this->authorize('update', $venta);

        $this->ventas->cerrar($venta);

        return back()->with(['mensaje' => 'Venta cerrada correctamente.', 'tipo' => 'success']);
    }

    public function cancelar(Request $request, Venta $venta): RedirectResponse
    {
        $this->authorize('update', $venta);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        $this->ventas->cancelar($venta, $datos['motivo']);

        return back()->with(['mensaje' => 'Venta cancelada; el inmueble vuelve al catálogo.', 'tipo' => 'success']);
    }

    public function subirEscritura(Request $request, Venta $venta): RedirectResponse
    {
        $this->authorize('update', $venta);

        $request->validate([
            'escritura' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ], [
            'escritura.mimes' => 'La escritura debe ser un archivo PDF.',
            'escritura.max' => 'El archivo no puede superar los 5 MB.',
        ]);

        $this->ventas->adjuntarEscritura($venta, $request->file('escritura'));

        return back()->with(['mensaje' => 'Escritura adjuntada correctamente.', 'tipo' => 'success']);
    }
}
