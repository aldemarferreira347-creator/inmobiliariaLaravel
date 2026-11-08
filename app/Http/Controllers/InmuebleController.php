<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\RolUsuario;
use App\Http\Requests\FiltroInmuebleRequest;
use App\Models\Inmueble;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Catálogo público de inmuebles (HU-01 / HU-02), sin autenticación requerida.
 *
 * Modelo principal: {@see Inmueble}. `filtrar()`, `disponibles()`
 * y `recientes()` son scopes del propio modelo; las cifras del home también
 * consultan {@see User} para contar clientes y asesores.
 */
class InmuebleController extends Controller
{
    private const DESTACADOS = 6;

    private const INMUEBLES_POR_PAGINA = 9;

    /** Portada pública: inmuebles destacados y cifras generales del sitio. */
    public function inicio(): View
    {
        return view('inmuebles.inicio', [
            'destacados' => Inmueble::disponibles()->recientes()->limit(self::DESTACADOS)->get(),
            'estadisticas' => $this->estadisticas(),
        ]);
    }

    /** Listado público de inmuebles (Disponible, Reservado y Ocupado), paginado y con los filtros de búsqueda aplicados. */
    public function index(FiltroInmuebleRequest $request): View
    {
        $filtros = $request->filtros();

        return view('inmuebles.index', [
            'inmuebles' => Inmueble::filtrar($filtros)
                ->publicos()
                ->recientes()
                ->paginate(self::INMUEBLES_POR_PAGINA)
                ->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    /** Ficha pública de un inmueble, con su galería de imágenes y si es favorito del visitante autenticado. */
    public function show(Inmueble $inmueble): View
    {
        return view('inmuebles.show', [
            'inmueble' => $inmueble->load('imagenes'),
            'esFavorito' => $inmueble->esFavoritoDe(request()->user()),
        ]);
    }

    /** Estado actual del inmueble en JSON; lo consulta el sondeo de la ficha (HU-09.3). */
    public function estado(Inmueble $inmueble): JsonResponse
    {
        return response()->json(['estado' => $inmueble->estado->value]);
    }

    /**
     * Cifras del home. Se cachean 10 minutos para no lanzar cuatro COUNT(*)
     * en cada visita a la portada.
     */
    private function estadisticas(): array
    {
        return Cache::remember('inicio.estadisticas', now()->addMinutes(10), fn () => [
            'inmuebles' => Inmueble::count(),
            'disponibles' => Inmueble::where('estado', EstadoInmueble::Disponible)->count(),
            'clientes' => User::delRol(RolUsuario::Cliente)->count(),
            'asesores' => User::delRol(RolUsuario::Asesor)->activos()->count(),
        ]);
    }
}
