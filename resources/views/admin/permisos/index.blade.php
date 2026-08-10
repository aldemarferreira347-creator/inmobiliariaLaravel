@extends('layouts.panel')

@section('titulo', 'Roles y permisos')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Roles y permisos</h1>
            <p class="subtitle">
                Consulta qué puede hacer cada rol dentro del sistema. Esta matriz es informativa:
                el control efectivo lo aplican el middleware de rol y las policies.
            </p>
        </div>
    </div>

    @foreach ($roles as $rol)
        <div class="panel-card">
            <div class="panel-card-header">
                <h2><x-icon name="shield" class="h-5 w-5" /> {{ $rol->nombre }}</h2>
                <p class="subtitle">{{ $rol->descripcion }}</p>
            </div>

            @if ($rol->permisos->isEmpty())
                <div class="empty-panel">
                    <div class="empty-icon"><x-icon name="shield-off" class="h-7 w-7" /></div>
                    <p>Este rol no tiene permisos registrados.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="panel-table" data-no-export data-page-size="25">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rol->permisos->sortBy(['modulo', 'accion']) as $permiso)
                                <tr>
                                    <td class="td-capitalize">{{ $permiso->modulo }}</td>
                                    <td><span class="badge accent-blue">{{ $permiso->accion_etiqueta }}</span></td>
                                    <td>
                                        <span class="badge {{ $permiso->activo ? 'badge-confirmada' : 'badge-cancelada' }}">
                                            {{ $permiso->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
@endsection
