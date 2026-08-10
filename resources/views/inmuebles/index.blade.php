@extends('layouts.app')

@section('titulo', 'Inmuebles')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="sparkles" class="h-3.5 w-3.5" /> Catálogo completo</span>
        <h1>Todos los inmuebles</h1>
        <p>Encuentra la propiedad perfecta para tu vida o tu negocio en Neiva</p>
    </div>

    <section>
        <div class="container propiedades-container">
            <x-flash />

            <div class="filtros">
                <h2><x-icon name="search" class="h-5 w-5" /> Buscar inmuebles</h2>

                <form class="filtro-inputs" method="GET" action="{{ route('inmuebles.index') }}">
                    <input type="text" name="codigo" placeholder="Código (Ej: INM-A1B2C3)"
                        value="{{ $filtros['codigo'] ?? '' }}" aria-label="Código del inmueble">

                    <input type="text" name="ubicacion" placeholder="Ciudad o barrio"
                        value="{{ $filtros['ubicacion'] ?? '' }}" aria-label="Ciudad o barrio">

                    <select name="modalidad" aria-label="Modalidad">
                        <option value="">Modalidad</option>
                        @foreach (\App\Enumerados\ModalidadInmueble::cases() as $modalidad)
                            <option value="{{ $modalidad->value }}" @selected(($filtros['modalidad'] ?? '') === $modalidad->value)>
                                {{ $modalidad->etiqueta() }}
                            </option>
                        @endforeach
                    </select>

                    <select name="tipo" aria-label="Tipo de inmueble">
                        <option value="">Tipo de propiedad</option>
                        @foreach (\App\Enumerados\TipoInmueble::cases() as $tipo)
                            <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? '') === $tipo->value)>
                                {{ $tipo->etiqueta() }}
                            </option>
                        @endforeach
                    </select>

                    <select name="precio_min" aria-label="Precio mínimo">
                        <option value="">Precio mínimo</option>
                        @foreach (\App\Soporte\RangosPrecio::TOPES as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected((int) ($filtros['precio_min'] ?? 0) === $valor)>
                                Desde {{ $etiqueta }}
                            </option>
                        @endforeach
                    </select>

                    <select name="precio_max" aria-label="Precio máximo">
                        <option value="">Precio máximo</option>
                        @foreach (\App\Soporte\RangosPrecio::TOPES as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected((int) ($filtros['precio_max'] ?? 0) === $valor)>
                                Hasta {{ $etiqueta }}
                            </option>
                        @endforeach
                    </select>

                    <select name="habitaciones" aria-label="Habitaciones">
                        <option value="">Habitaciones</option>
                        @foreach ([1 => '1 habitación', 2 => '2 habitaciones', 3 => '3 o más'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected((int) ($filtros['habitaciones'] ?? 0) === $valor)>
                                {{ $etiqueta }}
                            </option>
                        @endforeach
                    </select>

                    <div class="botones-filtro">
                        <button type="submit" class="btn-primary">Buscar</button>

                        @if ($filtros)
                            <a href="{{ route('inmuebles.index') }}" class="btn-limpiar">
                                <x-icon name="x" class="h-4 w-4" /> Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @if ($filtros)
                <p class="resultado-filtro">
                    <x-icon name="search" class="h-4 w-4" />
                    Se encontraron <strong>{{ $inmuebles->total() }}</strong> inmueble(s) con los filtros aplicados.
                </p>
            @endif

            <div class="inmuebles-grid">
                @forelse ($inmuebles as $inmueble)
                    <x-inmueble-card :inmueble="$inmueble" />
                @empty
                    <div class="empty-state">
                        <div class="empty-state-icon"><x-icon name="search" class="h-10 w-10" /></div>
                        <p class="empty-state-title">
                            {{ $filtros
                                ? 'No se encontraron inmuebles con los criterios seleccionados'
                                : 'No hay inmuebles disponibles en este momento' }}
                        </p>

                        @if ($filtros)
                            <a href="{{ route('inmuebles.index') }}" class="btn-primary btn-all-properties">
                                Modificar filtros
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

            {{ $inmuebles->links() }}
        </div>
    </section>
@endsection
