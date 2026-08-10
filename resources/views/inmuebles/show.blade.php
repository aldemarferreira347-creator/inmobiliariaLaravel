@extends('layouts.app')

@section('titulo', $inmueble->titulo)

@php
    $imagenes = $inmueble->imagenes;
    $precioPrincipal = collect($inmueble->precios)->firstWhere('tipo', '!=', 'consultar') ?? $inmueble->precios[0];
    $consultaMapa = urlencode("{$inmueble->direccion}, {$inmueble->barrio}, {$inmueble->ciudad}, Colombia");
@endphp

@section('contenido')
    <div class="detalle-page">
        <x-flash class="alerta-detalle" />

        <div class="detalle-back-bar">
            <a href="{{ route('inmuebles.index') }}" class="btn-volver">
                <x-icon name="arrow-left" class="h-4 w-4" /> Volver al catálogo
            </a>
        </div>

        <div class="detalle-hero-section">
            <div class="slider-column">
                <div class="main-slider-wrap">
                    <div class="slider-track" id="slider-track">
                        @forelse ($imagenes as $imagen)
                            <div class="slide-item">
                                <img src="{{ $imagen->url_publica }}" alt="{{ $inmueble->titulo }}"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                            </div>
                        @empty
                            <div class="slide-item">
                                <img src="{{ $inmueble->imagen_url }}" alt="{{ $inmueble->titulo }}">
                            </div>
                        @endforelse
                    </div>

                    @if ($imagenes->count() > 1)
                        <span class="slide-counter"><span id="slide-current">1</span> / {{ $imagenes->count() }}</span>

                        <button type="button" class="slider-btn prev-btn" id="slider-prev" aria-label="Imagen anterior">
                            <x-icon name="chevron-left" class="h-5 w-5" />
                        </button>
                        <button type="button" class="slider-btn next-btn" id="slider-next" aria-label="Imagen siguiente">
                            <x-icon name="chevron-right" class="h-5 w-5" />
                        </button>

                        <div class="slider-dots">
                            @foreach ($imagenes as $imagen)
                                <button type="button" class="slider-dot @if ($loop->first) active @endif"
                                    aria-label="Ir a la imagen {{ $loop->iteration }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($imagenes->count() > 1)
                    <div class="thumb-strip">
                        <div class="thumb-viewport">
                            <div class="thumb-list">
                                @foreach ($imagenes as $imagen)
                                    <div class="thumb-item @if ($loop->first) active-thumb @endif">
                                        <img src="{{ $imagen->url_publica }}" alt="Miniatura {{ $loop->iteration }}"
                                            loading="lazy">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="price-card-floating">
                @auth
                    <form method="POST" action="{{ route('favoritos.toggle', $inmueble) }}">
                        @csrf
                        <button type="submit" class="btn-toggle-fav-hero"
                            aria-label="{{ $esFavorito ? 'Quitar de favoritos' : 'Añadir a favoritos' }}">
                            <x-icon name="heart" class="h-5 w-5" :fill="$esFavorito ? 'currentColor' : 'none'" />
                        </button>
                    </form>
                @endauth

                <p class="price-label-small">{{ $precioPrincipal['label'] }}</p>
                <p class="price-main">{{ $precioPrincipal['valor'] }}</p>

                <div class="detalle-badges">
                    <x-estado-badge :estado="$inmueble->estado" base="badge-estado" />

                    @if ($inmueble->modalidad === \App\Enumerados\ModalidadInmueble::Ambos)
                        <span class="badge-modalidad">Venta</span>
                        <span class="badge-modalidad">Arriendo</span>
                    @else
                        <span class="badge-modalidad">{{ $inmueble->modalidad->etiqueta() }}</span>
                    @endif
                </div>

                <div class="codigo-inmueble-container">
                    <x-icon name="building" class="h-4 w-4" />
                    <span>Código:</span>
                    <strong>{{ $inmueble->codigo }}</strong>
                </div>

                @foreach ($inmueble->precios as $precio)
                    @continue($precio['tipo'] === $precioPrincipal['tipo'])
                    <div class="precio-card">
                        <span class="precio-label">{{ $precio['label'] }}</span>
                        <span class="precio-valor">{{ $precio['valor'] }}</span>
                    </div>
                @endforeach

                @auth
                    @if (auth()->user()->esCliente())
                        <form method="POST" action="{{ route('mensajes.iniciar', $inmueble) }}">
                            @csrf
                            <button type="submit" class="btn-contactar-asesor">
                                <x-icon name="message-square" class="h-5 w-5" /> Contactar asesor
                            </button>
                        </form>

                        @if ($inmueble->estado !== \App\Enumerados\EstadoInmueble::Ocupado)
                            @can('create', \App\Models\Cita::class)
                                <button type="button" class="btn-contactar-asesor" data-modal-abrir="modal-cita">
                                    <x-icon name="calendar" class="h-5 w-5" /> Agendar visita
                                </button>
                            @endcan
                        @endif
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-contactar-asesor">
                        <x-icon name="message-square" class="h-5 w-5" /> Contactar asesor
                    </a>
                @endauth

                @if ($inmueble->estado->esReservable())
                    <div class="reserva-cta-wrap">
                        <div class="reserva-cta-divider"><span>o</span></div>

                        @auth
                            @can('create', \App\Models\Reserva::class)
                                <button type="button" class="btn-reservar-inmueble" data-modal-abrir="modal-reserva">
                                    <x-icon name="lock" class="h-5 w-5" /> Reservar este inmueble
                                </button>
                                <p class="reserva-cta-hint">
                                    Bloquea el inmueble durante {{ \App\Models\Reserva::HORAS_PARA_PAGAR }} h mientras
                                    completas el pago.
                                </p>
                            @endcan
                        @else
                            <a href="{{ route('login') }}" class="btn-reservar-inmueble btn-reservar--login">
                                <x-icon name="key" class="h-5 w-5" /> Inicia sesión para reservar
                            </a>
                            <p class="reserva-cta-hint">Necesitas una cuenta para iniciar una reserva.</p>
                        @endauth
                    </div>
                @else
                    <div class="reserva-no-disponible">
                        <x-icon name="home" class="h-4 w-4" />
                        Este inmueble se encuentra actualmente {{ mb_strtolower($inmueble->estado->etiqueta()) }}.
                    </div>
                @endif
            </div>
        </div>

        <div class="main-content-grid">
            <div class="left-column">
                <div class="section-card">
                    <h1 class="detalle-titulo-principal">{{ $inmueble->titulo }}</h1>
                    <p class="detalle-ubicacion-text">
                        <x-icon name="map-pin" class="h-4 w-4" />
                        {{ $inmueble->direccion }}, {{ $inmueble->barrio }}, {{ $inmueble->ciudad }}
                    </p>

                    <h2 class="section-card-title">
                        <x-icon name="file-text" class="h-5 w-5" /> Descripción
                    </h2>
                    <p>{!! nl2br(e($inmueble->descripcion)) !!}</p>

                    <div class="amenities-grid">
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="bed" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ $inmueble->habitaciones }}<br>Habitaciones</span>
                        </div>
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="bath" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ $inmueble->banos }}<br>Baños</span>
                        </div>
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="ruler" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ number_format((float) $inmueble->area, 0, ',', '.') }}
                                m²<br>Área</span>
                        </div>
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="credit-card" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ $inmueble->parqueadero ? 'Con' : 'Sin' }}<br>Parqueadero</span>
                        </div>
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="building" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ $inmueble->tipo->etiqueta() }}<br>Tipo</span>
                        </div>
                        <div class="amenity-item">
                            <div class="amenity-icon"><x-icon name="shield" class="h-5 w-5" /></div>
                            <span class="amenity-label">{{ $inmueble->estado->etiqueta() }}<br>Estado</span>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-card-title"><x-icon name="map-pin" class="h-5 w-5" /> Ubicación</h2>

                    <div class="map-container">
                        <iframe src="https://maps.google.com/maps?q={{ $consultaMapa }}&output=embed&z=15&hl=es"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="Mapa de ubicación del inmueble" allowfullscreen></iframe>

                        <div class="map-address-card">
                            <p class="addr-main">{{ $inmueble->direccion }}, {{ $inmueble->barrio }}</p>
                            <p class="addr-sub">{{ $inmueble->ciudad }}, Colombia</p>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $consultaMapa }}"
                                target="_blank" rel="noopener" class="btn-maps">
                                Ver en Google Maps <x-icon name="arrow-up-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="right-column">
                <div class="contact-form-card">
                    <h2>Ficha del inmueble</h2>
                    <p class="form-subtitle">Datos registrados en el sistema</p>

                    <dl class="card-meta">
                        <div>
                            <dt>Código</dt>
                            <dd>{{ $inmueble->codigo }}</dd>
                        </div>
                        <div>
                            <dt>Tipo</dt>
                            <dd>{{ $inmueble->tipo->etiqueta() }}</dd>
                        </div>
                        <div>
                            <dt>Modalidad</dt>
                            <dd>{{ $inmueble->modalidad->etiqueta() }}</dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>{{ $inmueble->estado->etiqueta() }}</dd>
                        </div>
                        <div>
                            <dt>Ciudad</dt>
                            <dd>{{ $inmueble->ciudad }}</dd>
                        </div>
                        <div>
                            <dt>Barrio</dt>
                            <dd>{{ $inmueble->barrio }}</dd>
                        </div>
                        <div>
                            <dt>Publicado</dt>
                            <dd>{{ $inmueble->fecha_publicacion->format('d/m/Y') }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('inmuebles.index') }}" class="btn-contactar-asesor form-submit-mt">
                        <x-icon name="search" class="h-5 w-5" /> Ver inmuebles similares
                    </a>
                </div>
            </div>
        </div>

        @auth
            @can('create', \App\Models\Reserva::class)
                @if ($inmueble->estado->esReservable())
                    @include('reservas.partials.modal-solicitar')
                @endif
            @endcan

            @can('create', \App\Models\Cita::class)
                @if ($inmueble->estado !== \App\Enumerados\EstadoInmueble::Ocupado)
                    @include('citas.partials.modal-solicitar')
                @endif
            @endcan
        @endauth
    </div>
@endsection
