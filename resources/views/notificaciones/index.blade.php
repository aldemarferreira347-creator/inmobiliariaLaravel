@extends(auth()->user()->rol->usaPanel() ? 'layouts.panel' : 'layouts.app')

@section('titulo', 'Notificaciones')

@section(auth()->user()->rol->usaPanel() ? 'panel' : 'contenido')
    @unless (auth()->user()->rol->usaPanel())
        <div class="page-hero">
            <span class="page-hero-badge"><x-icon name="bell" class="h-3.5 w-3.5" /> Mi cuenta</span>
            <h1>Notificaciones</h1>
            <p>{{ $sinLeer }} sin leer · {{ $notificaciones->count() }} en total</p>
        </div>
    @endunless

    <div class="notif-page">
        <x-flash />

        <div class="notif-header">
            <div>
                <h2>Centro de notificaciones</h2>
                <p class="notif-subtitle">{{ $sinLeer }} sin leer · {{ $notificaciones->count() }} en total</p>
            </div>

            @if ($sinLeer > 0)
                <form method="POST" action="{{ route('notificaciones.leidas') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-marcar-todas">
                        <x-icon name="check-check" class="h-4 w-4" /> Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>

        @if ($notificaciones->isEmpty())
            <div class="notif-empty">
                <div class="notif-empty-icon"><x-icon name="bell" class="h-7 w-7" /></div>
                <h3>No tienes notificaciones</h3>
                <p>Aquí verás los avisos sobre tus reservas, contratos y mensajes.</p>
            </div>
        @else
            <div class="notif-list">
                @foreach ($notificaciones as $notificacion)
                    <article @class(['notif-card', 'no-leida' => ! $notificacion->estaLeida()])>
                        <div class="notif-icon {{ $notificacion->tipo->claseCss() }}">
                            <x-icon :name="$notificacion->tipo->icono()" class="h-4 w-4" />
                        </div>

                        <div class="notif-body">
                            <h4>{{ $notificacion->titulo }}</h4>
                            <p>{{ $notificacion->mensaje }}</p>

                            <span class="notif-time">
                                <x-icon name="clock" class="h-3 w-3" />
                                {{ $notificacion->creado_en->format('d/m/Y · H:i') }}
                            </span>

                            @if ($notificacion->enlace)
                                <a href="{{ $notificacion->enlace }}" class="btn-text">
                                    Ver detalle <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                                </a>
                            @endif
                        </div>

                        @unless ($notificacion->estaLeida())
                            <form method="POST" action="{{ route('notificaciones.leida', $notificacion) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="notif-mark" title="Marcar como leída">
                                    <x-icon name="check" class="h-4 w-4" />
                                </button>
                            </form>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
