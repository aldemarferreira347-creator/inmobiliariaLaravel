@extends('layouts.app')

@section('titulo', 'Mis reservas')

@php
    $pendientes = $totalesPorEstado[\App\Enumerados\EstadoReserva::PendientePago->value] ?? 0;
@endphp

@section('contenido')
    <div class="reservas-page">
        <div class="reservas-hero">
            <div class="reservas-hero-inner">
                <div>
                    <span class="reservas-hero-badge">
                        <x-icon name="clipboard-list" class="h-3.5 w-3.5" /> Panel de cliente
                    </span>
                    <h1>Mis reservas</h1>
                    <p>Consulta el estado de tus solicitudes y completa los pagos pendientes</p>
                </div>
            </div>
        </div>

        <div class="reservas-container">
            <x-flash />

            @if ($pendientes > 0)
                <div class="reservas-info-banner">
                    <span class="reservas-info-icon"><x-icon name="clock" class="h-5 w-5" /></span>
                    <div>
                        <strong>Tienes {{ $pendientes }} reserva(s) pendientes de pago.</strong>
                        Dispones de {{ \App\Models\Reserva::HORAS_PARA_PAGAR }} horas desde la solicitud para
                        registrar el pago; pasado ese plazo la reserva se cancela automáticamente.
                    </div>
                </div>
            @endif

            <div class="reservas-stats">
                <div class="reservas-stat-card">
                    <div class="rsc-icon"><x-icon name="clipboard-list" class="h-5 w-5" /></div>
                    <div class="rsc-value">{{ $reservas->count() }}</div>
                    <div class="rsc-label">Total</div>
                </div>
                <div class="reservas-stat-card">
                    <div class="rsc-icon"><x-icon name="clock" class="h-5 w-5" /></div>
                    <div class="rsc-value">{{ $pendientes }}</div>
                    <div class="rsc-label">Pendientes de pago</div>
                </div>
                <div class="reservas-stat-card">
                    <div class="rsc-icon"><x-icon name="circle-check" class="h-5 w-5" /></div>
                    <div class="rsc-value">{{ $totalesPorEstado[\App\Enumerados\EstadoReserva::Confirmada->value] ?? 0 }}</div>
                    <div class="rsc-label">Confirmadas</div>
                </div>
            </div>

            @if ($reservas->isEmpty())
                <div class="reservas-empty">
                    <div class="reservas-empty-icon"><x-icon name="clipboard-list" class="h-8 w-8" /></div>
                    <h2>Todavía no tienes reservas</h2>
                    <p>Explora el catálogo y reserva el inmueble que te interese.</p>
                    <a href="{{ route('inmuebles.index') }}" class="btn-reservas-primary">Ver catálogo</a>
                </div>
            @else
                <div class="reservas-grid">
                    @foreach ($reservas as $reserva)
                        <article class="reserva-card">
                            <img class="reserva-card-img" src="{{ $reserva->inmueble->imagen_url }}" alt=""
                                aria-hidden="true">

                            <div class="reserva-card-body">
                                <span class="reserva-badge {{ $reserva->estado->claseBadge() }}">
                                    <x-icon :name="$reserva->estado->icono()" class="h-3.5 w-3.5" />
                                    {{ $reserva->estado->etiqueta() }}
                                </span>

                                <span class="reserva-codigo">{{ $reserva->codigo_reserva }}</span>
                                <h3 class="reserva-inmueble-titulo">{{ $reserva->inmueble->titulo }}</h3>
                                <p class="reserva-inmueble-meta">
                                    <x-icon name="map-pin" class="h-3.5 w-3.5" /> {{ $reserva->inmueble->ubicacion }}
                                </p>
                                <p class="reserva-monto"><strong>{{ $reserva->monto_formateado }}</strong></p>

                                @if ($reserva->estado === \App\Enumerados\EstadoReserva::PendientePago)
                                    <span class="reserva-countdown" data-expira="{{ $reserva->expira_en->toIso8601String() }}">
                                        <span class="countdown-icon"><x-icon name="clock" class="h-3.5 w-3.5" /></span>
                                        <span class="countdown-texto" data-cuenta-atras-texto></span>
                                    </span>
                                @endif

                                <span class="reserva-fecha">Solicitada el
                                    {{ $reserva->created_at->format('d/m/Y H:i') }}</span>
                            </div>

                            <div class="reserva-card-footer">
                                <a href="{{ route('reservas.show', $reserva) }}" class="btn-reservas-outline">
                                    Ver detalle
                                </a>

                                @if ($reserva->admiteNuevoPago())
                                    <a href="{{ route('reservas.show', $reserva) }}#registrar-pago"
                                        class="btn-reservas-primary">
                                        <x-icon name="credit-card" class="h-4 w-4" /> Registrar pago
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
