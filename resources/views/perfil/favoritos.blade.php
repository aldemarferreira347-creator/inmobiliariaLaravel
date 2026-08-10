@extends('layouts.app')

@section('titulo', 'Mis favoritos')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="star" class="h-3.5 w-3.5" /> Mi cuenta</span>
        <h1>Mis favoritos</h1>
        <p>Los inmuebles que has guardado para revisar más tarde</p>
    </div>

    <section class="section-perfil">
        <div class="container">
            <x-flash />

            <div class="perfil-dashboard">
                @include('perfil.partials.sidebar')

                <main class="perfil-content">
                    <div class="inmuebles-grid">
                        @forelse ($inmuebles as $inmueble)
                            <x-inmueble-card :inmueble="$inmueble" quitable />
                        @empty
                            <div class="empty-state">
                                <div class="empty-state-icon"><x-icon name="star" class="h-10 w-10" /></div>
                                <p class="empty-state-title">Todavía no tienes inmuebles favoritos</p>
                                <p class="empty-state-sub">
                                    Marca con el corazón los inmuebles que te interesen y los verás aquí.
                                </p>
                                <a href="{{ route('inmuebles.index') }}" class="btn-primary btn-all-properties">
                                    Explorar catálogo
                                </a>
                            </div>
                        @endforelse
                    </div>
                </main>
            </div>
        </div>
    </section>
@endsection
