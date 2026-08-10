{{--
    Tarjeta de inmueble usada en las grillas del catálogo y de favoritos.
    `quitable` muestra el botón para retirarlo de favoritos.
--}}
@props(['inmueble', 'quitable' => false])

<article class="card" @if ($quitable) id="card-fav-{{ $inmueble->id }}" @endif>
    <div class="card-img-wrap">
        <img src="{{ $inmueble->imagen_url }}" alt="{{ $inmueble->titulo }}" width="600" height="400" loading="lazy"
            decoding="async" class="img-lazy-fade is-loaded">

        @if ($quitable)
            <form method="POST" action="{{ route('favoritos.toggle', $inmueble) }}">
                @csrf
                <button type="submit" class="btn-toggle-fav-card" aria-label="Quitar de favoritos">
                    <x-icon name="heart" class="h-5 w-5" fill="currentColor" />
                </button>
            </form>
        @endif

        <span class="codigo-badge">
            <x-icon name="tag" class="h-3.5 w-3.5" /> {{ $inmueble->codigo }}
        </span>
    </div>

    <div class="card-body">
        <x-estado-badge :estado="$inmueble->estado" />

        <h3>{{ $inmueble->titulo }}</h3>

        <p class="card-location">
            <x-icon name="map-pin" class="h-4 w-4" /> {{ $inmueble->ubicacion }}
        </p>

        <div class="card-features">
            <span><x-icon name="bed" class="h-4 w-4" /> {{ $inmueble->habitaciones }} hab.</span>
            <span><x-icon name="bath" class="h-4 w-4" /> {{ $inmueble->banos }} baños</span>
            <span><x-icon name="ruler" class="h-4 w-4" /> {{ (int) $inmueble->area }} m²</span>
        </div>

        <div class="precios-card">
            @foreach ($inmueble->precios as $precio)
                <p class="precio-card precio-{{ $precio['tipo'] }}">
                    <span class="precio-label">{{ $precio['label'] }}:</span>
                    <span class="precio-valor">{{ $precio['valor'] }}</span>
                </p>
            @endforeach
        </div>

        <a href="{{ route('inmuebles.show', $inmueble) }}" class="btn-primary btn-ver">
            Ver detalle <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    </div>
</article>
