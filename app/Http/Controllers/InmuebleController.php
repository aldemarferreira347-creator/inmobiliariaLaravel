<?php

namespace App\Http\Controllers;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\RolUsuario;
use App\Http\Requests\FiltroInmuebleRequest;
use App\Models\Inmueble;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

// HU-01 / HU-02: catálogo público de inmuebles
class InmuebleController extends Controller
{
    private const DESTACADOS = 6;

    private const INMUEBLES_POR_PAGINA = 9;

    public function inicio(): View
    {
        return view('inmuebles.inicio', [
            'destacados' => Inmueble::disponibles()->recientes()->limit(self::DESTACADOS)->get(),
            'estadisticas' => $this->estadisticas(),
        ]);
    }

    public function index(FiltroInmuebleRequest $request): View
    {
        $filtros = $request->filtros();

        return view('inmuebles.index', [
            'inmuebles' => Inmueble::filtrar($filtros)
                ->disponibles()
                ->recientes()
                ->paginate(self::INMUEBLES_POR_PAGINA)
                ->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    public function show(Inmueble $inmueble): View
    {
        return view('inmuebles.show', [
            'inmueble' => $inmueble->load('imagenes'),
            'esFavorito' => $inmueble->esFavoritoDe(request()->user()),
        ]);
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
