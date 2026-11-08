@extends('layouts.panel')

@section('titulo', 'Error en el reporte')

@section('panel')
    <a href="{{ route('admin.reportes.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver a reportes
    </a>

    <div class="panel-card">
        <div class="empty-panel" role="alert">
            <p>No se pudo generar el reporte. Es posible que el sistema presente fallas de conexión o esté
                temporalmente indisponible.</p>
            <a href="{{ $urlReintento }}" class="btn-filter-apply">Intentar nuevamente</a>
        </div>
    </div>
@endsection
