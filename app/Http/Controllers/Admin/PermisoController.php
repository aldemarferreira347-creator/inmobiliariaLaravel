<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\View\View;

/**
 * Consulta de la matriz de permisos por rol (HU-26.4), de solo lectura.
 *
 * Modelo principal: {@see Role}, con sus permisos asociados.
 */
class PermisoController extends Controller
{
    /** Muestra la matriz de permisos de cada rol del sistema. */
    public function index(): View
    {
        return view('admin.permisos.index', [
            'roles' => Role::with('permisos')->orderBy('id')->get(),
        ]);
    }
}
