@extends('layouts.panel')

@section('titulo', 'Citas')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Gestión de citas</h1>
            <p class="subtitle">Asigna un asesor a las visitas solicitadas y revisa el trabajo del equipo.</p>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="clock" class="h-5 w-5" /> Sin asignar</h2>
        </div>

        @if ($sinAsignar->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="calendar" class="h-7 w-7" /></div>
                <p>No hay citas pendientes de asignación.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="citas-sin-asignar">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Inmueble</th>
                            <th data-no-sort>Asignar asesor</th>
                            <th data-no-sort data-no-export-col></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sinAsignar as $cita)
                            <tr>
                                <td class="td-date">{{ $cita->fecha->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="td-title">{{ $cita->cliente->nombre }}</span>
                                    <span class="td-subtitle">{{ $cita->cliente->email }}</span>
                                </td>
                                <td>
                                    <span class="td-title">{{ $cita->inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $cita->inmueble->codigo }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.citas.asignar', $cita) }}"
                                        class="admin-citas-asignar-form" data-confirmar
                                        data-confirmar-tono="exito" data-confirmar-titulo="¿Asignar esta visita?"
                                        data-confirmar-texto="El asesor elegido recibirá una notificación con la fecha y el inmueble."
                                        data-confirmar-boton="Sí, asignar">
                                        @csrf
                                        <select name="asesor_id" required>
                                            <option value="">Elige un asesor</option>
                                            @foreach ($asesoresDisponibles as $asesor)
                                                <option value="{{ $asesor->id }}">{{ $asesor->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-icon btn-icon--success" title="Asignar">
                                            <x-icon name="check" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="{{ route('admin.citas.show', $cita) }}" class="btn-icon btn-icon--info"
                                        title="Ver detalle">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="users" class="h-5 w-5" /> Por asesor</h2>
        </div>

        @if ($porAsesor->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="users" class="h-7 w-7" /></div>
                <p>Todavía no hay citas asignadas a un asesor.</p>
            </div>
        @else
            @foreach ($porAsesor as $citasDelAsesor)
                @php($asesor = $citasDelAsesor->first()->asesor)
                <div class="panel-card-header panel-card-header--between mt-6">
                    <h3>{{ $asesor->nombre }}</h3>
                    <span class="td-subtitle">{{ $citasDelAsesor->count() }} cita(s)</span>
                </div>

                <div class="table-responsive">
                    <table class="panel-table" data-enhance-table
                        data-export-name="citas-{{ str($asesor->nombre)->slug() }}">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Inmueble</th>
                                <th>Estado</th>
                                <th data-no-sort>Reasignar</th>
                                <th data-no-sort data-no-export-col></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($citasDelAsesor as $cita)
                                <tr>
                                    <td class="td-date">{{ $cita->fecha->format('d/m/Y H:i') }}</td>
                                    <td>{{ $cita->cliente->nombre }}</td>
                                    <td>
                                        <span class="td-title">{{ $cita->inmueble->titulo }}</span>
                                        <span class="td-subtitle">{{ $cita->inmueble->codigo }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $cita->estado->claseBadge() }}">
                                            {{ $cita->estado->etiqueta() }}
                                        </span>
                                        @if ($cita->tieneObservacion())
                                            <x-icon name="file-check" class="h-4 w-4 text-emerald-600"
                                                aria-label="Con observaciones" />
                                        @endif
                                    </td>
                                    <td>
                                        @if ($cita->estado->admiteAsignacion())
                                            <form method="POST" action="{{ route('admin.citas.asignar', $cita) }}"
                                                class="admin-citas-asignar-form" data-confirmar
                                                data-confirmar-titulo="¿Reasignar esta visita a otro asesor?"
                                                data-confirmar-texto="El asesor anterior deja de verla y el nuevo recibirá una notificación."
                                                data-confirmar-boton="Sí, reasignar">
                                                @csrf
                                                <select name="asesor_id" required>
                                                    @foreach ($asesoresDisponibles as $otroAsesor)
                                                        <option value="{{ $otroAsesor->id }}"
                                                            @selected($otroAsesor->id === $asesor->id)>
                                                            {{ $otroAsesor->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn-icon btn-icon--edit" title="Reasignar">
                                                    <x-icon name="refresh-cw" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @else
                                            <span class="td-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.citas.show', $cita) }}"
                                            class="btn-icon btn-icon--info" title="Ver detalle">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>
@endsection
