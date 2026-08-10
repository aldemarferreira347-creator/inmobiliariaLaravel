@extends('layouts.panel')

@section('titulo', 'Detalle de cita')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>{{ $cita->inmueble->titulo }}</h1>
            <p class="subtitle">{{ $cita->fecha->format('d/m/Y \a \l\a\s H:i') }}</p>
        </div>
        <span class="badge {{ $cita->estado->claseBadge() }}">{{ $cita->estado->etiqueta() }}</span>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="user" class="h-5 w-5" /> Cliente</h2>
        </div>
        <div class="perfil-details">
            <div class="detail-item">
                <span class="rinfo-label">Nombre</span>
                <span>{{ $cita->cliente->nombre }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Correo</span>
                <span>{{ $cita->cliente->email }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Teléfono</span>
                <span>{{ $cita->cliente->telefono ?? '—' }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Inmueble</span>
                <a href="{{ route('inmuebles.show', $cita->inmueble) }}" class="panel-link">
                    {{ $cita->inmueble->codigo }} <x-icon name="external-link" class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="file-text" class="h-5 w-5" /> Observaciones de la visita</h2>
        </div>

        @if ($cita->estado === \App\Enumerados\EstadoCita::Realizada || $cita->observacion)
            @if ($cita->observacion)
                <p class="mb-4">{!! nl2br(e($cita->observacion->descripcion)) !!}</p>
            @endif

            <form method="POST" action="{{ route('asesor.citas.observacion.editar', $cita) }}">
                @csrf
                @method('PATCH')
                <div class="form-group">
                    <label for="observaciones">Editar observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="4" required maxlength="2000">{{ old('observaciones', $cita->observacion?->descripcion) }}</textarea>
                    @error('observaciones')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="save" class="h-4 w-4" /> Guardar cambios
                </button>
            </form>
        @elseif ($cita->estado === \App\Enumerados\EstadoCita::Asignada)
            <p class="subtitle mb-4">Registra el resultado de la visita para marcarla como realizada.</p>

            <form method="POST" action="{{ route('asesor.citas.observacion', $cita) }}">
                @csrf
                <div class="form-group">
                    <label for="observaciones">Observaciones <span class="req">*</span></label>
                    <textarea id="observaciones" name="observaciones" rows="4" required maxlength="2000"
                        placeholder="¿Cómo fue la visita? ¿Qué comentó el cliente?">{{ old('observaciones') }}</textarea>
                    @error('observaciones')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="circle-check" class="h-4 w-4" /> Marcar visita como realizada
                </button>
            </form>
        @else
            <p class="subtitle">Esta cita está {{ mb_strtolower($cita->estado->etiqueta()) }}; no admite observaciones.</p>
        @endif
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="clock" class="h-5 w-5" /> Historial</h2>
        </div>

        @if ($cita->historial->isEmpty())
            <p class="subtitle">Sin eventos registrados.</p>
        @else
            <ul class="cita-historial-list">
                @foreach ($cita->historial as $evento)
                    <li class="detail-item">
                        <span class="rinfo-label">{{ $evento->created_at->format('d/m/Y H:i') }} — {{ $evento->usuario->nombre }}</span>
                        <span>{{ $evento->accion }}: {{ $evento->descripcion }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
