{{-- Layout de los paneles internos: añade la barra lateral del rol al layout público --}}
@extends('layouts.app')

@section('sin_footer', true)

@section('contenido')
    <div class="panel-layout">
        @include('layouts.partials.sidebar')

        <main class="panel-main">
            <x-flash />

            @yield('panel')
        </main>
    </div>
@endsection
