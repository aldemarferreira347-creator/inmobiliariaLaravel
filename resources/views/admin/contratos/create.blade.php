@extends('layouts.panel')

@section('titulo', 'Emitir contrato')

@section('panel')
    <a href="{{ route('admin.contratos.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver al listado
    </a>

    <div class="panel-topbar">
        <div>
            <h1>Emitir contrato</h1>
            <p class="subtitle">
                Solo aparecen las reservas confirmadas sin contrato y dentro del plazo de
                {{ \App\Models\Contrato::DIAS_PARA_EMITIR }} días naturales desde su confirmación.
            </p>
        </div>
    </div>

    <div class="panel-card panel-form-card">
        @if ($reservas->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="clipboard-list" class="h-7 w-7" /></div>
                <p>No hay reservas confirmadas pendientes de contrato.</p>
                <a href="{{ route('admin.reservas.index') }}" class="btn-panel-primary">Ver reservas</a>
            </div>
        @else
            <form method="POST" action="{{ route('admin.contratos.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="form-group full">
                        <label for="reserva_id">Reserva <span class="req">*</span></label>
                        <select id="reserva_id" name="reserva_id" required data-reserva-monto-select>
                            @foreach ($reservas as $reserva)
                                <option value="{{ $reserva->id }}" data-monto="{{ $reserva->monto_formateado }}"
                                    @selected(old('reserva_id') == $reserva->id)>
                                    {{ $reserva->codigo_reserva }} — {{ $reserva->inmueble->titulo }}
                                    ({{ $reserva->cliente->nombre }})
                                </option>
                            @endforeach
                        </select>
                        @error('reserva_id')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de inicio <span class="req">*</span></label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio"
                            value="{{ old('fecha_inicio', now()->format('Y-m-d')) }}" required>
                        @error('fecha_inicio')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha de fin <span class="text-opcional">(opcional)</span></label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}">
                        <small class="field-note">Déjala vacía para un contrato indefinido.</small>
                        @error('fecha_fin')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="valor_mensual">Valor del contrato</label>
                        <input type="text" id="valor_mensual" class="input-readonly" disabled
                            data-reserva-monto-display>
                        <small class="field-note">
                            <x-icon name="lock" class="h-3 w-3" /> Es el precio de venta o arriendo ya fijado en la
                            reserva; no se puede editar aquí.
                        </small>
                    </div>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn-panel-primary">
                        <x-icon name="save" class="h-4 w-4" /> Emitir contrato
                    </button>
                    <a href="{{ route('admin.contratos.index') }}" class="btn-panel-cancelar">Cancelar</a>
                </div>
            </form>
        @endif
    </div>
@endsection
