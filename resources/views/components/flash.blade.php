{{--
    Mensajes de sesión y errores de validación.
    `class` permite usar la variante visual de cada contexto (.flash-msg en los
    paneles, .auth-alert en las pantallas de autenticación).
--}}
@props(['base' => 'flash-msg'])

@php
    $tipo = session('tipo', 'success');
    $clases = trim($attributes->get('class', $base));
@endphp

@if (session('mensaje'))
    <div class="{{ $clases }} {{ $tipo }}" role="status">
        <x-icon :name="\App\Soporte\Iconos::paraFlash($tipo)" class="h-4 w-4" />
        {{ session('mensaje') }}
    </div>
@endif

@if ($errors->any())
    <div class="{{ $clases }} error" role="alert">
        <x-icon name="circle-x" class="h-4 w-4" />
        {{ $errors->first() }}
    </div>
@endif
