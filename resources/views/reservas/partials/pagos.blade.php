{{-- Intentos de pago de una reserva; compartida por cliente y panel --}}
<div class="rdetalle-card">
    <h2 class="rdetalle-card-title"><x-icon name="credit-card" class="h-5 w-5" /> Intentos de pago</h2>

    @if ($reserva->pagos->isEmpty())
        <p class="subtitle">Todavía no se ha registrado ningún pago para esta reserva.</p>
    @else
        <div class="pagos-list">
            @foreach ($reserva->pagos as $pago)
                <div class="pago-item pago-{{ mb_strtolower($pago->estado->name) }}">
                    <div class="pago-header">
                        <span class="pago-estado-badge {{ $pago->estado->claseBadge() }}">
                            {{ $pago->estado->etiqueta() }}
                        </span>
                        <span class="pago-fecha">{{ $pago->created_at->format('d/m/Y H:i') }}</span>
                    </div>

                    <div class="pago-detalle">
                        <span><x-icon name="dollar-sign" class="h-3.5 w-3.5" /> {{ $pago->monto_formateado }}</span>
                        <span><x-icon name="credit-card" class="h-3.5 w-3.5" /> {{ $pago->metodo_pago->etiqueta() }}</span>
                        @if ($pago->referencia)
                            <span><x-icon name="tag" class="h-3.5 w-3.5" /> {{ $pago->referencia }}</span>
                        @endif
                        @if ($pago->revisor)
                            <span><x-icon name="user" class="h-3.5 w-3.5" /> Revisado por {{ $pago->revisor->nombre }}</span>
                        @endif
                    </div>

                    @if ($pago->motivo_rechazo)
                        <p class="pago-rechazo">{{ $pago->motivo_rechazo }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
