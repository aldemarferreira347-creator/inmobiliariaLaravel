@extends('layouts.panel')

@section('titulo', 'Contratos')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Contratos</h1>
            <p class="subtitle">Contratos de arriendo emitidos a partir de reservas confirmadas.</p>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header panel-card-header--between">
            <h2>Listado de contratos</h2>
            <a href="{{ route('admin.contratos.create') }}" class="btn-panel-primary">
                <x-icon name="plus" class="h-4 w-4" /> Emitir contrato
            </a>
        </div>

        @if ($contratos->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="file-text" class="h-7 w-7" /></div>
                <p>No hay registros disponibles.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="contratos">
                    <thead>
                        <tr>
                            <th>N.º contrato</th>
                            <th>Inmueble</th>
                            <th>Cliente</th>
                            <th>Vigencia</th>
                            <th>Valor mensual</th>
                            <th>Estado</th>
                            <th data-no-sort data-no-export-col>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contratos as $contrato)
                            <tr>
                                <td><code class="code-badge">{{ $contrato->numero_contrato }}</code></td>
                                <td>
                                    <span class="td-title">{{ $contrato->reserva->inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $contrato->reserva->inmueble->codigo }}</span>
                                </td>
                                <td>{{ $contrato->reserva->cliente->nombre }}</td>
                                <td class="td-nowrap">{{ $contrato->vigencia }}</td>
                                <td class="td-price">{{ $contrato->valor_formateado }}</td>
                                <td>
                                    <span class="badge {{ $contrato->estado->claseBadge() }}">
                                        {{ $contrato->estado->etiqueta() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.contratos.show', $contrato) }}"
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
