@extends('layouts.panel')

@section('titulo', 'Reporte de '.$reporte->titulo())

@php
    $tipo = $reporte->tipo();
    $filas = $reporte->filas();
    $numericas = $reporte->columnasNumericas();
@endphp

@section('panel')
    <a href="{{ route('admin.reportes.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver a reportes
    </a>

    <div class="panel-topbar">
        <div>
            <h1>Reporte de {{ $reporte->titulo() }}</h1>
            <p class="subtitle">{{ $tipo->descripcion() }} · {{ $filtro->descripcion() }}</p>
        </div>

        <div class="export-buttons">
            <a href="{{ route('admin.reportes.excel', [$tipo->value, ...request()->query()]) }}"
                class="btn-export btn-export-csv">
                <x-icon name="download" class="h-4 w-4" /> Excel
            </a>
            <a href="{{ route('admin.reportes.pdf', [$tipo->value, ...request()->query()]) }}"
                class="btn-export btn-export-pdf">
                <x-icon name="file-text" class="h-4 w-4" /> PDF
            </a>
        </div>
    </div>

    @include('admin.reportes.partials.filtros')

    <div class="stat-cards">
        @foreach ($reporte->resumen() as $etiqueta => $valor)
            <div class="stat-card-panel">
                <div class="stat-value stat-value--sm">{{ $valor }}</div>
                <div class="stat-label">{{ $etiqueta }}</div>
            </div>
        @endforeach
    </div>

    <div class="panel-card">
        <div class="panel-card-header panel-card-header--between">
            <h2>Detalle</h2>
            <span class="reporte-table-count">{{ $filas->count() }} registro(s)</span>
        </div>

        @if ($filas->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon :name="$tipo->icono()" class="h-7 w-7" /></div>
                <p>No hay registros disponibles para los filtros seleccionados.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="{{ $tipo->value }}"
                    data-page-size="25">
                    <thead>
                        <tr>
                            @foreach ($reporte->columnas() as $columna)
                                <th>{{ $columna }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                @foreach ($fila as $indice => $celda)
                                    <td @class(['td-price' => in_array($indice, $numericas, true)])>
                                        {{ in_array($indice, $numericas, true)
                                            ? '$'.number_format((float) $celda, 0, ',', '.')
                                            : $celda }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
