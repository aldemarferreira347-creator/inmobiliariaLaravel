{{-- Línea de tiempo de los cambios de estado de una reserva; compartida por cliente y panel --}}
<div class="rdetalle-card">
    <h2 class="rdetalle-card-title"><x-icon name="clock" class="h-5 w-5" /> Historial de la reserva</h2>

    <div class="historial-timeline">
        @foreach ($reserva->historial as $entrada)
            <div class="historial-item">
                <span class="historial-dot"></span>

                <div class="historial-content">
                    <div class="historial-estados">
                        @if ($entrada->estado_anterior)
                            <span class="h-estado-de">{{ $entrada->estado_anterior }}</span>
                            <span class="h-arrow">→</span>
                        @endif
                        <span class="h-estado-a">{{ $entrada->estado_nuevo }}</span>
                    </div>

                    <p class="historial-comentario">{{ $entrada->comentario }}</p>

                    <span class="historial-meta">
                        <x-icon :name="$entrada->autor ? 'user' : 'monitor'" class="h-3 w-3" />
                        {{ $entrada->autor_nombre }} · {{ $entrada->creado_en->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</div>
