@extends('layouts.panel')

@section('titulo', 'Reportes')

@section('panel')
    <div class="panel-topbar flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1>Reportes del sistema</h1>
            <p class="subtitle">Análisis integral de inmuebles, reservas, clientes e ingresos. Generado el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.reportes.pdf', [\App\Enumerados\TipoReporte::Integral->value, ...$filtro->comoParametros()]) }}" class="btn-panel-edit">
                <x-icon name="file-text" class="h-4 w-4" /> Exportar PDF
            </a>
            <a href="{{ route('admin.reportes.excel', [\App\Enumerados\TipoReporte::Integral->value, ...$filtro->comoParametros()]) }}" class="btn-panel-edit">
                <x-icon name="download" class="h-4 w-4" /> Exportar Excel
            </a>
        </div>
    </div>

    @include('admin.reportes.partials.filtros')

    @foreach (\App\Enumerados\TipoReporte::cases() as $tipo)
        @if ($tipo === \App\Enumerados\TipoReporte::Integral)
            @continue
        @endif
        <div class="panel-card">
            <div class="panel-card-header panel-card-header--between">
                <h2><x-icon :name="$tipo->icono()" class="h-5 w-5" /> {{ $tipo->etiqueta() }}</h2>
                <a href="{{ route('admin.reportes.show', [$tipo->value, ...$filtro->comoParametros()]) }}"
                    class="btn-panel-primary">
                    Ver reporte <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <p class="subtitle">{{ $tipo->descripcion() }}</p>

            <div class="stat-cards">
                @foreach ($resumenes[$tipo->value] as $etiqueta => $valor)
                    <div class="stat-card-panel">
                        <div class="stat-value stat-value--sm">{{ $valor }}</div>
                        <div class="stat-label">{{ $etiqueta }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
