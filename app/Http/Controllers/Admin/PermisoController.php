<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\View\View;

// HU-26.4: consulta de la matriz de permisos por rol
class PermisoController extends Controller
{
    public function index(): View
    {
        return view('admin.permisos.index', [
            'roles' => Role::with('permisos')->orderBy('id')->get(),
        ]);
    }
}
