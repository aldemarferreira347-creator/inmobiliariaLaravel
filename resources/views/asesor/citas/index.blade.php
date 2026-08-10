@extends('layouts.panel')

@section('titulo', 'Mis citas')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Mis citas</h1>
            <p class="subtitle">Visitas que tienes asignadas.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('asesor.citas.index') }}" class="admin-reservas-filtros">
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach (\App\Enumerados\EstadoCita::cases() as $estado)
                    <option value="{{ $estado->value }}" @selected($filtroEstado === $estado->value)>
                        {{ $estado->etiqueta() }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($filtroEstado)
            <a href="{{ route('asesor.citas.index') }}" class="btn-filter-reset">Limpiar</a>
        @endif
    </form>

    <div class="panel-card">
        @if ($citas->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="calendar" class="h-7 w-7" /></div>
                <p>No tienes citas asignadas en este momento.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Inmueble</th>
                            <th>Estado</th>
                            <th data-no-sort>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($citas as $cita)
                            <tr>
                                <td class="td-date">{{ $cita->fecha->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="td-title">{{ $cita->cliente->nombre }}</span>
                                    <span class="td-subtitle">{{ $cita->cliente->telefono ?? $cita->cliente->email }}</span>
                                </td>
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
                                    <a href="{{ route('asesor.citas.show', $cita) }}" class="btn-icon btn-icon--info"
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
@endsection
