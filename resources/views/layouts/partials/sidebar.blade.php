{{-- Barra lateral del panel; cada rol interno tiene su propio menú --}}
@php($usuario = auth()->user())

<aside class="panel-sidebar" aria-label="Menú del panel">
    <div class="sidebar-brand">
        <span>Panel de control</span>
        <h3>{{ $usuario->rol->etiqueta() }}</h3>
    </div>

    <div class="sidebar-user">
        <img src="{{ $usuario->foto }}" alt="" aria-hidden="true">
        <div class="sidebar-user-info">
            <strong>{{ $usuario->nombre }}</strong>
            <span>{{ $usuario->email }}</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Secciones del panel">
        @if ($usuario->esAdministrador())
            @include('layouts.partials.sidebar-admin')
        @else
            @include('layouts.partials.sidebar-asesor')
        @endif

        <a href="{{ route('inmuebles.index') }}">
            <x-icon name="search" class="nav-icon" /> Ver catálogo
        </a>
        <a href="{{ route('perfil.edit') }}" @class(['active' => request()->routeIs('perfil.*')])>
            <x-icon name="user" class="nav-icon" /> Mi perfil
        </a>
    </nav>
</aside>
