<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f1e4a">
    <meta name="description"
        content="Inmobiliaria García — Encuentra apartamentos, casas y locales en Neiva. Compra, venta y arriendo de inmuebles con asesoría personalizada.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logosolo.svg') }}">
    <title>@yield('titulo', 'Inicio') — Inmobiliaria García</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body @auth data-url-avisos="{{ route('mensajes.sin-leer') }}" @endauth>
    <a href="#contenido" class="skip-link">Saltar al contenido</a>

    @include('layouts.partials.nav')

    <div id="contenido" tabindex="-1">
        @yield('contenido')
    </div>

    @unless (View::hasSection('sin_footer'))
        @include('layouts.partials.footer')
    @endunless

    @stack('scripts')
</body>

</html>
