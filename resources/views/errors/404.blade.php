@extends('layouts.app')

@section('titulo', 'Página no encontrada')

@section('contenido')
    <div class="error-page">
        <span class="error-glow error-glow--gold" aria-hidden="true"></span>
        <span class="error-glow error-glow--white" aria-hidden="true"></span>

        <div class="error-content">
            <p class="error-code">404</p>
            <h1 class="error-title">No encontramos lo que buscabas</h1>
            <p class="error-page-text">
                La página o el inmueble al que intentas acceder no existe, cambió de dirección
                o ya no está publicado.
            </p>

            <div class="error-actions">
                <a href="{{ route('inicio') }}" class="btn-hero">
                    <x-icon name="home" class="h-5 w-5" /> Volver al inicio
                </a>
                <a href="{{ route('inmuebles.index') }}" class="btn-hero-outline">
                    <x-icon name="search" class="h-5 w-5" /> Ver catálogo
                </a>
            </div>
        </div>
    </div>
@endsection
