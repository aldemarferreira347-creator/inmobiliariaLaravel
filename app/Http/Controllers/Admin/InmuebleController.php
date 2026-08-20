<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InmuebleRequest;
use App\Models\Inmueble;
use App\Servicios\ImagenInmuebleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD de inmuebles del panel de administración (HU-04 / HU-08).
 *
 * Modelo principal: {@see \App\Models\Inmueble}. También coordina:
 * - {@see ImagenInmuebleService} para guardar y eliminar las fotos del inmueble.
 * - La relación Inmueble::reservas() (vía tieneReservasActivas()) para impedir
 *   borrar inmuebles con reservas vigentes.
 *
 * Rutas: Route::resource('inmuebles', ...) dentro del grupo "admin." en
 * routes/web.php -> admin.inmuebles.{index,store,show,update,destroy}.
 */
class InmuebleController extends Controller
{
    public function __construct(private readonly ImagenInmuebleService $imagenes) {}

    /** Lista los inmuebles del panel con su galería precargada y el total de inmuebles por estado. */
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

    /** Registra un inmueble nuevo con su código autogenerado y sus imágenes iniciales. */
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

    /** Muestra el detalle de un inmueble para el modal de edición, con su estado recalculado en vivo. */
    public function show(Inmueble $inmueble): View
    {
        return view('admin.inmuebles.index', [
            'inmueble' => $inmueble->load('imagenes'),
            'estadoCalculado' => $inmueble->estadoCalculado(),
        ]);
    }

    /** Actualiza los datos de un inmueble existente y agrega las imágenes nuevas subidas en el formulario. */
    public function update(InmuebleRequest $request, Inmueble $inmueble): RedirectResponse
    {
        DB::transaction(function () use ($request, $inmueble) {
            $inmueble->update($request->safe()->except('imagenes'));
            $this->imagenes->agregar($inmueble, $request->file('imagenes', []));
        });

        return $this->volverAlListado("Inmueble «{$inmueble->titulo}» actualizado correctamente.");
    }

    /** Elimina un inmueble junto con sus imágenes; se bloquea si tiene reservas activas asociadas. */
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

    /** Redirige al listado del panel con un mensaje flash de éxito o error. */
    private function volverAlListado(string $mensaje, string $tipo = 'success'): RedirectResponse
    {
        return redirect()
            ->route('admin.inmuebles.index')
            ->with(['mensaje' => $mensaje, 'tipo' => $tipo]);
    }
}
