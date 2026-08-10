{{-- Etiqueta de estado del inmueble; `base` elige entre las dos variantes del diseño --}}
@props(['estado', 'base' => 'estado'])

<span class="{{ $base }} {{ $estado->claseCss() }}">{{ $estado->etiqueta() }}</span>
