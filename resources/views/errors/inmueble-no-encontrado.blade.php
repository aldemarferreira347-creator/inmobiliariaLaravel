@extends('layouts.app')

@section('titulo', 'Inmueble no encontrado')

@section('contenido')
    <div class="error-page">
        <span class="error-glow error-glow--gold" aria-hidden="true"></span>
        <span class="error-glow error-glow--white" aria-hidden="true"></span>

        <div class="error-content">
            <p class="error-code">404</p>
            <h1 class="error-title">Inmueble no encontrado</h1>
            <p class="error-page-text">
                El inmueble que buscas no existe o ya no está publicado.
            </p>

            <div class="error-actions">
                <a href="{{ route('inmuebles.index') }}" class="btn-hero">
                    <x-icon name="search" class="h-5 w-5" /> Volver al catálogo
                </a>
            </div>
        </div>
    </div>
@endsection
