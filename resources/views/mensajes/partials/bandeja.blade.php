{{--
    Bandeja de mensajes: lista de hilos a la izquierda y hilo activo a la derecha.
    La comparten la vista del cliente y la del panel.
--}}
@php($usuario = auth()->user())

<section class="chat-section">
    <div class="container">
        <x-flash />

        <div @class(['mensajes-layout', 'mensajes-layout--tall', 'has-active-chat' => $conversacion])>
            <div class="mensajes-lista">
                <div class="mensajes-lista-header">
                    <h2>Conversaciones</h2>
                    <input type="search" class="search-bar" placeholder="Buscar..." data-filtro-chats
                        aria-label="Buscar conversaciones">
                </div>

                <div class="mensajes-lista-scroll">
                    @forelse ($conversaciones as $hilo)
                        @php($otro = $hilo->interlocutorDe($usuario))
                        @php($sinLeer = $hilo->mensajesSinLeerPara($usuario))

                        <a href="{{ route('mensajes.show', $hilo) }}"
                            @class(['chat-item', 'active' => $conversacion?->is($hilo)])
                            data-nombre="{{ mb_strtolower($otro->nombre) }}">
                            <img src="{{ $otro->foto }}" alt="" aria-hidden="true"
                                class="h-10 w-10 rounded-full object-cover">

                            <div class="chat-item-info">
                                <div class="chat-item-top">
                                    <strong>{{ $otro->nombre }}</strong>
                                    <span class="chat-time">{{ $hilo->ultimo_mensaje_en?->format('d/m') }}</span>
                                </div>
                                <span class="chat-item-preview">
                                    {{ $hilo->ultimoMensaje?->resumen ?? 'Sin mensajes todavía' }}
                                </span>
                            </div>

                            @if ($sinLeer > 0)
                                <span class="chat-unread-badge">{{ $sinLeer }}</span>
                            @endif
                        </a>
                    @empty
                        <div class="empty-panel">
                            <div class="empty-icon"><x-icon name="message-square" class="h-7 w-7" /></div>
                            <p>Todavía no tienes conversaciones.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="chat-window">
                @if (! $conversacion)
                    <div class="chat-window-empty">Selecciona una conversación para ver los mensajes.</div>
                @else
                    @php($otro = $conversacion->interlocutorDe($usuario))

                    <div class="chat-header-panel">
                        <a href="{{ route('mensajes.index') }}" class="chat-back-btn" aria-label="Volver">
                            <x-icon name="arrow-left" class="h-4 w-4" />
                        </a>

                        <img src="{{ $otro->foto }}" alt="" aria-hidden="true"
                            class="h-10 w-10 rounded-full object-cover">

                        <div class="chat-header-info">
                            <strong>{{ $otro->nombre }}</strong>
                            <span>{{ $otro->rol->etiqueta() }}</span>
                        </div>

                        @if ($conversacion->inmueble)
                            <a href="{{ route('inmuebles.show', $conversacion->inmueble) }}"
                                class="chat-header-inmueble">
                                <strong>
                                    <x-icon name="building" class="h-3.5 w-3.5" />
                                    {{ $conversacion->inmueble->codigo }}
                                </strong>
                                {{ $conversacion->inmueble->titulo }}
                            </a>
                        @endif
                    </div>

                    <div class="chat-messages" data-chat
                        data-url-anteriores="{{ route('mensajes.anteriores', $conversacion) }}"
                        data-url-nuevos="{{ route('mensajes.nuevos', $conversacion) }}">
                        <div class="chat-loadmore" data-cargar-mas-contenedor>
                            <button type="button" class="btn-load-more" data-cargar-mas>
                                <x-icon name="refresh-cw" class="h-4 w-4" /> Cargar mensajes anteriores
                            </button>
                        </div>

                        @foreach ($mensajes as $mensaje)
                            @if ($loop->first || ! $mensaje->creado_en->isSameDay($mensajes[$loop->index - 1]->creado_en))
                                <div class="chat-date-sep">
                                    <span>{{ $mensaje->creado_en->translatedFormat('d \d\e F Y') }}</span>
                                </div>
                            @endif

                            <x-mensajes.burbuja :mensaje="$mensaje" :propio="$mensaje->emisor_id === $usuario->id" />
                        @endforeach
                    </div>

                    <form class="chat-input-area" method="POST" action="{{ route('mensajes.store', $conversacion) }}"
                        enctype="multipart/form-data" data-chat-form>
                        @csrf

                        <button type="button" class="chat-attach-btn" data-adjuntar aria-label="Adjuntar imagen">
                            <x-icon name="paperclip" class="h-5 w-5" />
                        </button>
                        <input type="file" name="adjunto" class="hidden" data-adjunto-input
                            accept="image/jpeg,image/png,image/webp">

                        <textarea name="contenido" rows="1" placeholder="Escribe un mensaje..." data-chat-texto
                            aria-label="Mensaje"></textarea>

                        <button type="submit" class="chat-send-btn" aria-label="Enviar">
                            <x-icon name="send" class="h-4 w-4" />
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
