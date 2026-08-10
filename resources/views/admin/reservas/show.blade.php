@extends('layouts.panel')

@section('titulo', 'Reserva '.$reserva->codigo_reserva)

@section('panel')
    <a href="{{ route('admin.reservas.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver al listado
    </a>

    <div class="panel-topbar">
        <div>
            <h1>Reserva {{ $reserva->codigo_reserva }}</h1>
            <p class="subtitle">{{ $reserva->inmueble->titulo }} · {{ $reserva->cliente->nombre }}</p>
        </div>

        <span class="reserva-badge-lg {{ $reserva->estado->claseBadge() }}">
            <x-icon :name="$reserva->estado->icono()" class="h-4 w-4" /> {{ $reserva->estado->etiqueta() }}
        </span>
    </div>

    <div class="admin-rdetalle-grid">
        <div class="admin-rdetalle-left">
            <div class="panel-card">
                <div class="panel-card-header">
                    <h2><x-icon name="file-text" class="h-5 w-5" /> Datos de la reserva</h2>
                </div>

                <div class="rdetalle-info-grid">
                    <div class="rinfo-item">
                        <span class="rinfo-label">Monto</span>
                        <span class="rinfo-value rinfo-monto">{{ $reserva->monto_formateado }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Solicitada</span>
                        <span class="rinfo-value">{{ $reserva->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Vence</span>
                        <span class="rinfo-value">{{ $reserva->expira_en->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Contrato</span>
                        <span class="rinfo-value">
                            @if ($reserva->contrato)
                                <a href="{{ route('admin.contratos.show', $reserva->contrato) }}" class="btn-text">
                                    {{ $reserva->contrato->numero_contrato }}
                                </a>
                            @else
                                Sin emitir
                            @endif
                        </span>
                    </div>
                    <div class="rinfo-item rinfo-item--full">
                        <span class="rinfo-label">Inmueble</span>
                        <span class="rinfo-value">
                            <a href="{{ route('admin.inmuebles.show', $reserva->inmueble) }}" class="btn-text">
                                {{ $reserva->inmueble->codigo }} — {{ $reserva->inmueble->titulo }}
                            </a>
                        </span>
                    </div>
                    <div class="rinfo-item rinfo-item--full">
                        <span class="rinfo-label">Cliente</span>
                        <span class="rinfo-value">
                            {{ $reserva->cliente->nombre }} · {{ $reserva->cliente->email }}
                            @if ($reserva->cliente->telefono)
                                · {{ $reserva->cliente->telefono }}
                            @endif
                        </span>
                    </div>
                    @if ($reserva->notas_cliente)
                        <div class="rinfo-item rinfo-item--full">
                            <span class="rinfo-label">Notas del cliente</span>
                            <span class="rinfo-value">{{ $reserva->notas_cliente }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if ($pagoEnRevision)
                {{-- Revisión del pago: sustituye a la confirmación automática de la pasarela --}}
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h2><x-icon name="credit-card" class="h-5 w-5" /> Pago pendiente de revisión</h2>
                    </div>

                    <div class="rdetalle-info-grid">
                        <div class="rinfo-item">
                            <span class="rinfo-label">Monto declarado</span>
                            <span class="rinfo-value rinfo-monto">{{ $pagoEnRevision->monto_formateado }}</span>
                        </div>
                        <div class="rinfo-item">
                            <span class="rinfo-label">Método</span>
                            <span class="rinfo-value">{{ $pagoEnRevision->metodo_pago->etiqueta() }}</span>
                        </div>
                        <div class="rinfo-item">
                            <span class="rinfo-label">Referencia</span>
                            <span class="rinfo-value">{{ $pagoEnRevision->referencia ?: '—' }}</span>
                        </div>
                        <div class="rinfo-item">
                            <span class="rinfo-label">Registrado</span>
                            <span class="rinfo-value">{{ $pagoEnRevision->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>

                    <form method="POST"
                        action="{{ route('admin.reservas.pagos.revisar', [$reserva, $pagoEnRevision]) }}"
                        class="form-mt-8" data-confirmar data-confirmar-tono="exito"
                        data-confirmar-titulo="¿Aprobar este pago?"
                        data-confirmar-texto="La reserva quedará confirmada y el inmueble pasará a Reservado. Se le enviará el comprobante al cliente."
                        data-confirmar-boton="Sí, aprobar">
                        @csrf
                        <input type="hidden" name="decision" value="aprobar">
                        <button type="submit" class="btn-panel-success">
                            <x-icon name="circle-check" class="h-4 w-4" /> Aprobar pago y confirmar reserva
                        </button>
                    </form>

                    <form method="POST"
                        action="{{ route('admin.reservas.pagos.revisar', [$reserva, $pagoEnRevision]) }}"
                        class="form-mt-8" data-confirmar
                        data-confirmar-titulo="¿Rechazar este pago?"
                        data-confirmar-texto="La reserva volverá a Pendiente de pago y se avisará al cliente con el motivo indicado."
                        data-confirmar-boton="Sí, rechazar">
                        @csrf
                        <input type="hidden" name="decision" value="rechazar">

                        <div class="form-group">
                            <label for="motivo_rechazo">Motivo del rechazo</label>
                            <input type="text" id="motivo_rechazo" name="motivo_rechazo" maxlength="255"
                                placeholder="Ej: la referencia no coincide con ningún movimiento">
                            @error('motivo_rechazo')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn-panel-danger">
                            <x-icon name="circle-x" class="h-4 w-4" /> Rechazar pago
                        </button>
                    </form>
                </div>
            @endif

            @can('cancelar', $reserva)
                <div class="panel-card danger-zone">
                    <h3 class="danger-zone-title">
                        <x-icon name="triangle-alert" class="h-4 w-4" /> Cancelar la reserva
                    </h3>

                    <form method="POST" action="{{ route('admin.reservas.cancelar', $reserva) }}" data-confirmar
                        data-confirmar-titulo="¿Cancelar esta reserva?"
                        data-confirmar-texto="El inmueble volverá al estado que le corresponda y se avisará al cliente."
                        data-confirmar-boton="Sí, cancelar">
                        @csrf

                        <div class="form-group">
                            <label for="motivo">Motivo <span class="req">*</span></label>
                            <input type="text" id="motivo" name="motivo" maxlength="255" required>
                            @error('motivo')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn-panel-danger">
                            <x-icon name="ban" class="h-4 w-4" /> Cancelar reserva
                        </button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="admin-rdetalle-right">
            @include('reservas.partials.pagos')
            @include('reservas.partials.historial')
        </div>
    </div>
@endsection
