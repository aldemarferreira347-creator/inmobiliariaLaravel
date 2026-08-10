<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InmuebleRequest;
use App\Models\Inmueble;
use App\Servicios\ImagenInmuebleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// HU-04 / HU-08: CRUD de inmuebles del panel de administración
class InmuebleController extends Controller
{
    public function __construct(private readonly ImagenInmuebleService $imagenes) {}

    public function index(): View
    {
        return view('admin.inmuebles.index', [
            // Cada fila abre su propio modal de edición con su galería: sin
            // el eager load, pintar esos N modales dispararía una consulta
            // de imágenes por inmueble (N+1).
            'inmuebles' => Inmueble::recientes()->with('imagenes')->get(),
            'totalesPorEstado' => Inmueble::query()
                ->selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado'),
        ]);
    }

    // El código se genera aquí: el formulario lo muestra en solo lectura
    public function store(InmuebleRequest $request): RedirectResponse
    {
        $inmueble = DB::transaction(function () use ($request) {
            $inmueble = Inmueble::create([
                ...$request->safe()->except('imagenes'),
                'codigo' => Inmueble::generarCodigo(),
            ]);

            $this->imagenes->agregar($inmueble, $request->file('imagenes', []));

            return $inmueble;
        });

        return $this->volverAlListado("Inmueble «{$inmueble->titulo}» registrado con el código {$inmueble->codigo}.");
    }

    public function show(Inmueble $inmueble): View
    {
        return view('admin.inmuebles.show', [
            'inmueble' => $inmueble->load('imagenes'),
            'estadoCalculado' => $inmueble->estadoCalculado(),
        ]);
    }

    public function update(InmuebleRequest $request, Inmueble $inmueble): RedirectResponse
    {
        DB::transaction(function () use ($request, $inmueble) {
            $inmueble->update($request->safe()->except('imagenes'));
            $this->imagenes->agregar($inmueble, $request->file('imagenes', []));
        });

        return $this->volverAlListado("Inmueble «{$inmueble->titulo}» actualizado correctamente.");
    }

    // Un inmueble con reservas vivas conserva su histórico y no se elimina (HU-04.5)
    public function destroy(Inmueble $inmueble): RedirectResponse
    {
        $this->authorize('delete', $inmueble);

        if ($inmueble->tieneReservasActivas()) {
            return $this->volverAlListado(
                'No se puede eliminar: el inmueble tiene reservas activas asociadas.',
                'error'
            );
        }

        $titulo = $inmueble->titulo;

        DB::transaction(function () use ($inmueble) {
            $this->imagenes->eliminarTodas($inmueble);
            $inmueble->delete();
        });

        return $this->volverAlListado("Inmueble «{$titulo}» eliminado correctamente.");
    }

    private function volverAlListado(string $mensaje, string $tipo = 'success'): RedirectResponse
    {
        return redirect()
            ->route('admin.inmuebles.index')
            ->with(['mensaje' => $mensaje, 'tipo' => $tipo]);
    }
}
