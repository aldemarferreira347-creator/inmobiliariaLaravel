@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <section class="hero-section">
        <div class="hero-particles" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>

        <div class="hero-content fade-up">
            <span class="hero-badge">
                <x-icon name="sparkles" class="h-4 w-4" /> Neiva, Huila — Colombia
            </span>

            <h1>Encuentra tu <span>hogar ideal</span><br>con Inmobiliaria García</h1>

            <p>
                Apartamentos, casas y locales en las mejores ubicaciones de Neiva.<br>
                Compra, venta y arriendo con asesoría personalizada.
            </p>

            <div class="hero-actions">
                <a href="{{ route('inmuebles.index') }}" class="btn-hero">
                    <x-icon name="building" class="h-5 w-5" /> Ver inmuebles
                </a>
                @guest
                    <a href="{{ route('register') }}" class="btn-hero-outline">
                        <x-icon name="user-plus" class="h-5 w-5" /> Registrarme gratis
                    </a>
                @endguest
            </div>
        </div>
    </section>

    <div class="container">
        <div class="stats-strip fade-up fade-up-delay-1">
            <div class="stat-item">
                <span class="stat-number">+{{ $estadisticas['inmuebles'] }}</span>
                <span class="stat-label">Inmuebles</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">+{{ $estadisticas['disponibles'] }}</span>
                <span class="stat-label">Disponibles</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">+{{ $estadisticas['clientes'] }}</span>
                <span class="stat-label">Clientes felices</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">+{{ $estadisticas['asesores'] }}</span>
                <span class="stat-label">Asesores activos</span>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="filtros fade-up fade-up-delay-2">
            <h2><x-icon name="search" class="h-5 w-5" /> Buscar inmuebles</h2>

            <form class="filtro-inputs" method="GET" action="{{ route('inmuebles.index') }}">
                <input type="text" name="codigo" placeholder="Código (Ej: INM-A1B2C3)" aria-label="Código del inmueble">

                <select name="modalidad" aria-label="Modalidad">
                    <option value="">Modalidad</option>
                    @foreach (\App\Enumerados\ModalidadInmueble::cases() as $modalidad)
                        <option value="{{ $modalidad->value }}">{{ $modalidad->etiqueta() }}</option>
                    @endforeach
                </select>

                <select name="precio_max" aria-label="Precio máximo">
                    <option value="">Precio máximo</option>
                    @foreach (\App\Soporte\RangosPrecio::TOPES as $valor => $etiqueta)
                        <option value="{{ $valor }}">Hasta {{ $etiqueta }}</option>
                    @endforeach
                </select>

                <select name="habitaciones" aria-label="Habitaciones">
                    <option value="">Habitaciones</option>
                    <option value="1">1 habitación</option>
                    <option value="2">2 habitaciones</option>
                    <option value="3">3 o más</option>
                </select>

                <button type="submit" class="btn-primary">Buscar ahora</button>
            </form>
        </section>

        <section class="seccion-titulo">
            <h2>Inmuebles destacados</h2>
            <p class="seccion-subtitulo">Las mejores propiedades disponibles para ti</p>
        </section>

        <div class="inmuebles-grid">
            @forelse ($destacados as $inmueble)
                <x-inmueble-card :inmueble="$inmueble" />
            @empty
                <div class="empty-state">
                    <div class="empty-state-icon"><x-icon name="building" class="h-10 w-10" /></div>
                    <p class="empty-state-title">No hay inmuebles disponibles en este momento</p>
                    <p class="empty-state-sub">Vuelve pronto, estamos cargando nuevas propiedades.</p>
                </div>
            @endforelse
        </div>

        @if ($destacados->isNotEmpty())
            <div class="ver-todos-wrap">
                <a href="{{ route('inmuebles.index') }}" class="btn-hero">
                    Ver todos los inmuebles <x-icon name="arrow-right" class="h-5 w-5" />
                </a>
            </div>
        @endif
    </div>

    <section class="seccion-porque">
        <div class="container">
            <div class="seccion-titulo">
                <h2>¿Por qué elegirnos?</h2>
                <p class="seccion-subtitulo">Tu confianza es nuestra prioridad</p>
            </div>

            <div class="info-grid">
                <div class="tool-card fade-up">
                    <div class="tool-card-icon"><x-icon name="building" class="h-7 w-7" /></div>
                    <h3>Variedad de propiedades</h3>
                    <p>Amplio catálogo con apartamentos, casas y locales comerciales para todos los presupuestos y
                        necesidades.</p>
                </div>
                <div class="tool-card fade-up fade-up-delay-1">
                    <div class="tool-card-icon"><x-icon name="shield" class="h-7 w-7" /></div>
                    <h3>Transacciones seguras</h3>
                    <p>Respaldamos cada operación con asesoría legal especializada y contratos verificados para tu
                        tranquilidad.</p>
                </div>
                <div class="tool-card fade-up fade-up-delay-2">
                    <div class="tool-card-icon"><x-icon name="phone" class="h-7 w-7" /></div>
                    <h3>Soporte personalizado</h3>
                    <p>Nuestros asesores te acompañan en cada paso del proceso, desde la búsqueda hasta la firma del
                        contrato.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2>¿Listo para encontrar tu propiedad ideal?</h2>
            <p>Regístrate gratis y accede a los mejores inmuebles de Neiva con atención personalizada.</p>
            <div class="cta-actions">
                @guest
                    <a href="{{ route('register') }}" class="btn-hero">
                        Comenzar ahora <x-icon name="arrow-right" class="h-5 w-5" />
                    </a>
                @endguest
                <a href="{{ route('inmuebles.index') }}" class="btn-hero-outline">Explorar inmuebles</a>
            </div>
        </div>
    </section>
@endsection
