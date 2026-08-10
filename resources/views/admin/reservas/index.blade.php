@extends('layouts.panel')

@section('titulo', 'Reservas')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Reservas</h1>
            <p class="subtitle">Sigue el estado de las reservas y revisa los pagos declarados por los clientes.</p>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card-panel">
            <div class="stat-icon"><x-icon name="clipboard-list" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado->sum() }}</div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon stat-icon--gold"><x-icon name="clock" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoReserva::PendientePago->value] ?? 0 }}</div>
            <div class="stat-label">Pendientes de pago</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon"><x-icon name="refresh-cw" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoReserva::ProcesandoPago->value] ?? 0 }}</div>
            <div class="stat-label">En revisión</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon stat-icon--success"><x-icon name="circle-check" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoReserva::Confirmada->value] ?? 0 }}</div>
            <div class="stat-label">Confirmadas</div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reservas.index') }}" class="admin-reservas-filtros">
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                @foreach (\App\Enumerados\EstadoReserva::cases() as $estado)
                    <option value="{{ $estado->value }}" @selected(($filtros['estado'] ?? '') === $estado->value)>
                        {{ $estado->etiqueta() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}">
        </div>

        <div class="form-group">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
        </div>

        <button type="submit" class="btn-filter-apply">Filtrar</button>

        @if ($filtros)
            <a href="{{ route('admin.reservas.index') }}" class="btn-filter-reset">Limpiar</a>
        @endif
    </form>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2>Listado de reservas</h2>
        </div>

        @if ($reservas->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="clipboard-list" class="h-7 w-7" /></div>
                <p>No hay registros disponibles con los filtros aplicados.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="reservas">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Inmueble</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th data-no-sort data-no-export-col>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservas as $reserva)
                            <tr>
                                <td><code class="code-badge">{{ $reserva->codigo_reserva }}</code></td>
                                <td class="td-date">{{ $reserva->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="td-title">{{ $reserva->inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $reserva->inmueble->codigo }}</span>
                                </td>
                                <td>
                                    <span class="td-title">{{ $reserva->cliente->nombre }}</span>
                                    <span class="td-subtitle">{{ $reserva->cliente->email }}</span>
                                </td>
                                <td class="td-price">{{ $reserva->monto_formateado }}</td>
                                <td>
                                    <span class="badge {{ $reserva->estado->claseBadge() }}">
                                        {{ $reserva->estado->etiqueta() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.reservas.show', $reserva) }}"
                                            class="btn-icon btn-icon--info" title="Ver detalle">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
