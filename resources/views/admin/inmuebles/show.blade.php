@extends('layouts.panel')

@section('titulo', 'Detalle del inmueble')

@section('panel')
    <a href="{{ route('admin.inmuebles.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver al listado
    </a>

    <div class="panel-topbar">
        <div>
            <h1>{{ $inmueble->titulo }}</h1>
            <p class="subtitle"><code class="code-badge">{{ $inmueble->codigo }}</code> · {{ $inmueble->ubicacion }}</p>
        </div>
        <div class="row-actions">
            <a href="{{ route('admin.inmuebles.index', ['editar' => $inmueble->id]) }}" class="btn-panel-primary">
                <x-icon name="pencil" class="h-4 w-4" /> Editar
            </a>
            <a href="{{ route('inmuebles.show', $inmueble) }}" class="btn-panel-edit">
                <x-icon name="eye" class="h-4 w-4" /> Ver en el catálogo
            </a>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="building" class="h-5 w-5" /> Datos generales</h2>
        </div>

        <div class="rdetalle-info-grid">
            <div class="rinfo-item">
                <span class="rinfo-label">Tipo</span>
                <span class="rinfo-value">{{ $inmueble->tipo->etiqueta() }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Modalidad</span>
                <span class="rinfo-value">{{ $inmueble->modalidad->etiqueta() }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Estado guardado</span>
                <span class="rinfo-value"><x-estado-badge :estado="$inmueble->estado" base="badge-estado" /></span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Estado según reservas y contratos</span>
                <span class="rinfo-value"><x-estado-badge :estado="$estadoCalculado" base="badge-estado" /></span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Habitaciones</span>
                <span class="rinfo-value">{{ $inmueble->habitaciones }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Baños</span>
                <span class="rinfo-value">{{ $inmueble->banos }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Área</span>
                <span class="rinfo-value">{{ number_format((float) $inmueble->area, 0, ',', '.') }} m²</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Parqueadero</span>
                <span class="rinfo-value">{{ $inmueble->parqueadero ? 'Sí' : 'No' }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Publicado</span>
                <span class="rinfo-value">{{ $inmueble->fecha_publicacion->format('d/m/Y H:i') }}</span>
            </div>
            <div class="rinfo-item">
                <span class="rinfo-label">Precios</span>
                <span class="rinfo-value">
                    @foreach ($inmueble->precios as $precio)
                        <span class="rinfo-monto">{{ $precio['valor'] }}</span>@if (! $loop->last) · @endif
                    @endforeach
                </span>
            </div>
            <div class="rinfo-item rinfo-item--full">
                <span class="rinfo-label">Dirección</span>
                <span class="rinfo-value">{{ $inmueble->direccion }}, {{ $inmueble->barrio }},
                    {{ $inmueble->ciudad }}</span>
            </div>
            <div class="rinfo-item rinfo-item--full">
                <span class="rinfo-label">Descripción</span>
                <span class="rinfo-value">{{ $inmueble->descripcion }}</span>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="image" class="h-5 w-5" /> Galería ({{ $inmueble->imagenes->count() }})</h2>
        </div>

        @if ($inmueble->imagenes->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="image" class="h-7 w-7" /></div>
                <p>Este inmueble aún no tiene imágenes.</p>
            </div>
        @else
            <div class="img-gallery">
                @foreach ($inmueble->imagenes as $imagen)
                    <div @class(['img-gallery-item', 'img-gallery-item--principal' => $imagen->es_principal])>
                        @if ($imagen->es_principal)
                            <div class="img-principal-tag">Portada</div>
                        @endif
                        <img src="{{ $imagen->url_publica }}" alt="" aria-hidden="true" class="img-thumb">
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
