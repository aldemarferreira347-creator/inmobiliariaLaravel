@extends('layouts.app')

@section('titulo', 'Mensajes')

@section('sin_footer', true)

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="message-square" class="h-3.5 w-3.5" /> Mi cuenta</span>
        <h1>Mensajes</h1>
        <p>Conversa con tu asesor sobre los inmuebles que te interesan</p>
    </div>

    @include('mensajes.partials.bandeja')
@endsection
