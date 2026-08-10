@extends('layouts.panel')

@section('titulo', 'Mensajes')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Mensajes de clientes</h1>
            <p class="subtitle">Atiende las consultas sobre los inmuebles del catálogo.</p>
        </div>
    </div>

    @include('mensajes.partials.bandeja')
@endsection
