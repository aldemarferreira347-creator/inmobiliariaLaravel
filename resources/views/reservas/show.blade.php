@extends('layouts.app')

@section('titulo', 'Reserva '.$reserva->codigo_reserva)

@section('contenido')
    <div class="reservas-page">
        <div class="reservas-backbar">
            <a href="{{ route('reservas.index') }}" class="btn-back-reserva">
                <x-icon name="arrow-left" class="h-4 w-4" /> Volver a mis reservas
            </a>
        </div>

        <div class="reservas-container">
            <x-flash />

            <div class="rdetalle-header">
                <div>
                    <span class="rdetalle-codigo">{{ $reserva->codigo_reserva }}</span>
                    <h1>{{ $reserva->inmueble->titulo }}</h1>
                    <p class="rdetalle-meta">
                        <x-icon name="map-pin" class="h-4 w-4" /> {{ $reserva->inmueble->ubicacion }}
                    </p>
                </div>

                <span class="reserva-badge-lg {{ $reserva->estado->claseBadge() }}">
                    <x-icon :name="$reserva->estado->icono()" class="h-4 w-4" /> {{ $reserva->estado->etiqueta() }}
                </span>
            </div>

            <div class="rdetalle-grid">
                <div class="rdetalle-left">
                    <div class="rdetalle-card">
                        <h2 class="rdetalle-card-title">
                            <x-icon name="file-text" class="h-5 w-5" /> Información de la reserva
                        </h2>

                        <div class="rdetalle-info-grid">
                            <div class="rinfo-item">
                                <span class="rinfo-label">Código</span>
                                <span class="rinfo-value rinfo-code">{{ $reserva->codigo_reserva }}</span>
                            </div>
                            <div class="rinfo-item">
                                <span class="rinfo-label">Monto</span>
                                <span class="rinfo-value rinfo-monto">{{ $reserva->monto_formateado }}</span>
                            </div>
                            <div class="rinfo-item">
                                <span class="rinfo-label">Solicitada</span>
                                <span class="rinfo-value">{{ $reserva->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="rinfo-item">
                                <span class="rinfo-label">Plazo de pago</span>
                                <span class="rinfo-value">
                                    @if ($reserva->estado === \App\Enumerados\EstadoReserva::PendientePago)
                                        <span class="countdown-inline"
                                            data-expira="{{ $reserva->expira_en->toIso8601String() }}">
                                            <span data-cuenta-atras-texto></span>
                                        </span>
                                    @else
                                        {{ $reserva->expira_en->format('d/m/Y H:i') }}
                                    @endif
                                </span>
                            </div>
                            <div class="rinfo-item rinfo-item--full">
                                <span class="rinfo-label">Inmueble</span>
                                <span class="rinfo-value">
                                    <a href="{{ route('inmuebles.show', $reserva->inmueble) }}" class="btn-text">
                                        {{ $reserva->inmueble->codigo }} — {{ $reserva->inmueble->titulo }}
                                    </a>
                                </span>
                            </div>
                            @if ($reserva->notas_cliente)
                                <div class="rinfo-item rinfo-item--full">
                                    <span class="rinfo-label">Tus notas</span>
                                    <span class="rinfo-value">{{ $reserva->notas_cliente }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($reserva->admiteNuevoPago())
                        @can('registrarPago', $reserva)
                            @if ($tarjetas->isNotEmpty())
                                <div class="rdetalle-card rdetalle-card--upload">
                                    <h2 class="rdetalle-card-title">
                                        <x-icon name="credit-card" class="h-5 w-5" /> Pagar con tarjeta guardada
                                    </h2>
                                    <p class="subtitle rdetalle-hint">
                                        Paga {{ $reserva->monto_formateado }} al instante, sin volver a ingresar tus datos.
                                    </p>

                                    <div class="tarjetas-pago-list">
                                        @foreach ($tarjetas as $tarjeta)
                                            <form method="POST"
                                                action="{{ route('reservas.pagar-con-tarjeta', [$reserva, $tarjeta]) }}"
                                                class="tarjeta-pago-item">
                                                @csrf
                                                <span><x-icon name="credit-card" class="h-4 w-4" /> {{ $tarjeta->descripcion }}</span>
                                                <button type="submit" class="btn-panel-primary">
                                                    Pagar ahora
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>

                                    <div class="reserva-cta-divider"><span>o paga manualmente</span></div>
                                </div>
                            @endif
                        @endcan

                        <div class="rdetalle-card rdetalle-card--upload" id="registrar-pago">
                            <h2 class="rdetalle-card-title">
                                <x-icon name="credit-card" class="h-5 w-5" /> Registrar el pago
                            </h2>

                            @if ($rechazado = $reserva->ultimoPagoRechazado())
                                <div class="rdetalle-rechazo-alert">
                                    Tu intento anterior fue rechazado: {{ $rechazado->motivo_rechazo }}
                                </div>
                            @endif

                            <p class="subtitle rdetalle-hint">
                                Indica cómo realizaste el pago de {{ $reserva->monto_formateado }}. Un administrador lo
                                verificará y confirmará tu reserva.
                            </p>

                            <form method="POST" action="{{ route('reservas.pago', $reserva) }}">
                                @csrf

                                <div class="form-group">
                                    <label for="metodo_pago">Método de pago <span class="req">*</span></label>
                                    <select id="metodo_pago" name="metodo_pago" required>
                                        @foreach (\App\Enumerados\MetodoPago::cases() as $metodo)
                                            <option value="{{ $metodo->value }}" @selected(old('metodo_pago') === $metodo->value)>
                                                {{ $metodo->etiqueta() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="referencia">Número de referencia
                                        <span class="text-opcional">(opcional)</span></label>
                                    <input type="text" id="referencia" name="referencia" value="{{ old('referencia') }}"
                                        maxlength="150" placeholder="Ej: REF-00012345">
                                    <small class="field-note">Nos ayuda a localizar el movimiento más rápido.</small>
                                </div>

                                <button type="submit" class="btn-upload-submit">
                                    <x-icon name="send" class="h-4 w-4" /> Enviar para revisión
                                </button>
                            </form>
                        </div>
                    @endif

                    @can('cancelar', $reserva)
                        <div class="rdetalle-cancelar-wrap">
                            <form method="POST" action="{{ route('reservas.cancelar', $reserva) }}" data-confirmar
                                data-confirmar-titulo="¿Cancelar la reserva?"
                                data-confirmar-texto="El inmueble volverá a quedar disponible para otros clientes."
                                data-confirmar-boton="Sí, cancelar">
                                @csrf
                                <button type="submit" class="btn-cancelar-reserva">
                                    <x-icon name="ban" class="h-4 w-4" /> Cancelar reserva
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>

                <div class="rdetalle-right">
                    @include('reservas.partials.pagos')
                    @include('reservas.partials.historial')
                </div>
            </div>
        </div>
    </div>
@endsection
