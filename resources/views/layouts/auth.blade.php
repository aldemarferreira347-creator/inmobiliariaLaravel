{{-- Layout de las pantallas de autenticación --}}
@extends('layouts.app')

@section('contenido')
    <div class="auth-shell">
        <div @class(['auth-card', 'auth-card--wide' => ($ancha ?? false)])>
            <a href="{{ route('inicio') }}" class="auth-brand">
                <span class="logo-badge"><x-icon name="building" class="h-5 w-5" /></span>
                Inmobiliaria García
            </a>

            <x-flash class="auth-alert" />

            @yield('formulario')
        </div>
    </div>
@endsection
