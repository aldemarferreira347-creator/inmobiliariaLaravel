{{-- Burbuja de un mensaje del hilo --}}
@props(['mensaje', 'propio'])

<div @class(['msg-group', 'outgoing' => $propio, 'incoming' => ! $propio]) data-msg-id="{{ $mensaje->id }}">
    <span class="msg-sender-label">{{ $mensaje->emisor->nombre }}</span>

    @if ($mensaje->tieneAdjunto())
        <a href="{{ $mensaje->adjunto_publico }}" target="_blank" rel="noopener" class="msg-attachment">
            <img src="{{ $mensaje->adjunto_publico }}" alt="Imagen adjunta" loading="lazy">
        </a>
    @endif

    @if (filled($mensaje->contenido))
        <div class="msg-bubble">{{ $mensaje->contenido }}</div>
    @endif

    <span class="msg-time">
        {{ $mensaje->creado_en->format('H:i') }}
        @if ($propio)
            <span class="msg-status">
                <x-icon :name="$mensaje->leido_en ? 'check-check' : 'check'" class="h-3 w-3" />
            </span>
        @endif
    </span>
</div>
