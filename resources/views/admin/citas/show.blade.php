@extends('layouts.panel')

@section('titulo', 'Detalle de cita')

@section('panel')
    <a href="{{ route('admin.citas.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver a gestión de citas
    </a>

    <div class="panel-topbar">
        <div>
            <h1>Detalle de cita <span class="td-muted">#{{ $cita->id }}</span></h1>
        </div>
        <span class="badge {{ $cita->estado->claseBadge() }}">{{ $cita->estado->etiqueta() }}</span>
    </div>

    <div class="admin-rdetalle-grid">
        <div class="panel-card">
            <div class="panel-card-header">
                <h2><x-icon name="user" class="h-5 w-5" /> Información del cliente</h2>
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
                    <span class="rinfo-label">Fecha / hora</span>
                    <span><strong>{{ $cita->fecha->format('d/m/Y H:i') }}</strong></span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-header">
                <h2><x-icon name="building" class="h-5 w-5" /> Inmueble a visitar</h2>
            </div>
            <div class="perfil-details">
                <div class="detail-item">
                    <span class="rinfo-label">Código</span>
                    <span>{{ $cita->inmueble->codigo }}</span>
                </div>
                <div class="detail-item">
                    <span class="rinfo-label">Título</span>
                    <span>{{ $cita->inmueble->titulo }}</span>
                </div>
                <div class="detail-item">
                    <span class="rinfo-label">Dirección</span>
                    <span>{{ $cita->inmueble->direccion }}</span>
                </div>
                <div class="detail-item">
                    <span class="rinfo-label">Ciudad</span>
                    <span>{{ $cita->inmueble->ciudad }}</span>
                </div>
                <div class="detail-item rinfo-item--full">
                    <a href="{{ route('inmuebles.show', $cita->inmueble) }}" target="_blank" rel="noopener noreferrer"
                        class="btn-text">
                        Ver ficha del inmueble <x-icon name="external-link" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="pencil" class="h-5 w-5" /> Observaciones del asesor</h2>
        </div>

        @if ($cita->observacion)
            <p>{!! nl2br(e($cita->observacion->descripcion)) !!}</p>
            <div class="pago-detalle form-mt-8">
                @if ($cita->asesor)
                    <span><x-icon name="user" class="h-3.5 w-3.5" /> Registrado por: <strong>{{ $cita->asesor->nombre }}</strong></span>
                @endif
                <span><x-icon name="calendar" class="h-3.5 w-3.5" /> Fecha: <strong>{{ $cita->observacion->created_at->format('d/m/Y H:i') }}</strong></span>
                @if ($cita->observacion->updated_at->ne($cita->observacion->created_at))
                    <span><x-icon name="pencil" class="h-3.5 w-3.5" /> Última edición: <strong>{{ $cita->observacion->updated_at->format('d/m/Y H:i') }}</strong></span>
                @endif
            </div>
        @elseif ($cita->estado === \App\Enumerados\EstadoCita::Realizada)
            <div class="alert error">
                <x-icon name="triangle-alert" class="h-4 w-4" />
                <span>Esta cita está marcada como realizada pero <strong>no tiene observaciones registradas</strong>.</span>
            </div>
        @elseif ($cita->estado === \App\Enumerados\EstadoCita::Asignada)
            <div class="alert info">
                <x-icon name="circle-help" class="h-4 w-4" />
                <span>La cita aún no ha sido realizada. Las observaciones estarán disponibles una vez el asesor complete la visita.</span>
            </div>
        @else
            <p class="subtitle">Sin observaciones registradas.</p>
        @endif
    </div>

    @if ($cita->historial->isNotEmpty())
        <div class="panel-card">
            <div class="panel-card-header">
                <h2><x-icon name="clock" class="h-5 w-5" /> Historial de la cita</h2>
            </div>
            <ul class="audit-list">
                @foreach ($cita->historial as $evento)
                    <li>
                        <span class="audit-date">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                        <span>
                            <strong class="audit-action">{{ $evento->accion }}</strong>
                            — {{ $evento->descripcion }}
                            <span class="td-muted">· por {{ $evento->usuario->nombre }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
