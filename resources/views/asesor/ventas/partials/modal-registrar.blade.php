{{-- Alta de una venta sobre un inmueble disponible (HU-14.1) --}}
<div class="modal-overlay" id="modal-venta" @if ($errors->any()) data-modal-abierto @endif>
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2>Registrar venta</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-venta" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        @if ($inmueblesDisponibles->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="building" class="h-7 w-7" /></div>
                <p>No hay inmuebles disponibles para iniciar una venta.</p>
            </div>
        @else
            <form method="POST" action="{{ route('asesor.ventas.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="form-group full">
                        <label for="inmueble_id">Inmueble <span class="req">*</span></label>
                        <select id="inmueble_id" name="inmueble_id" required>
                            @foreach ($inmueblesDisponibles as $inmueble)
                                <option value="{{ $inmueble->id }}" @selected(old('inmueble_id') == $inmueble->id)>
                                    {{ $inmueble->codigo }} — {{ $inmueble->titulo }}
                                </option>
                            @endforeach
                        </select>
                        @error('inmueble_id')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="usuario_id">Comprador <span class="req">*</span></label>
                        <select id="usuario_id" name="usuario_id" required>
                            @foreach ($clientes as $cliente)
                                <option value="{{ $cliente->id }}" @selected(old('usuario_id') == $cliente->id)>
                                    {{ $cliente->nombre }} — {{ $cliente->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('usuario_id')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="precio_venta">Precio de venta <span class="req">*</span></label>
                        <input type="number" id="precio_venta" name="precio_venta" min="1" step="1000"
                            value="{{ old('precio_venta') }}" required>
                        @error('precio_venta')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="fecha_venta">Fecha de la venta <span class="req">*</span></label>
                        <input type="date" id="fecha_venta" name="fecha_venta"
                            value="{{ old('fecha_venta', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}"
                            required>
                        @error('fecha_venta')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="notaria">Notaría <span class="text-opcional">(opcional)</span></label>
                        <input type="text" id="notaria" name="notaria" value="{{ old('notaria') }}" maxlength="150">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" data-modal-cerrar="modal-venta">Cancelar</button>
                    <button type="submit" class="btn-panel-primary">
                        <x-icon name="save" class="h-4 w-4" /> Registrar venta
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
