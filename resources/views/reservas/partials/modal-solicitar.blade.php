{{--
    Solicitud de reserva desde la ficha del inmueble (HU-07.1).
    El monto no viaja en el formulario: lo calcula el servidor a partir del
    inmueble y la modalidad elegida.
--}}
<div class="modal-overlay" id="modal-reserva" @if ($errors->any()) data-modal-abierto @endif>
    <div class="modal-reserva-box">
        <div class="modal-reserva-header">
            <h2><x-icon name="lock" class="h-5 w-5" /> Iniciar reserva</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-reserva" aria-label="Cerrar">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="modal-reserva-body">
            <div class="modal-inmueble-info">
                <img src="{{ $inmueble->imagen_url }}" alt="" aria-hidden="true">
                <div>
                    <strong>{{ $inmueble->titulo }}</strong>
                    <span><x-icon name="map-pin" class="h-3.5 w-3.5" /> {{ $inmueble->ubicacion }}</span>
                    <span class="modal-precio">{{ $inmueble->precios[0]['valor'] }}</span>
                </div>
            </div>

            <div class="modal-pasos">
                <div class="modal-paso">
                    <span class="paso-num">1</span><span>Envías la solicitud de reserva</span>
                </div>
                <div class="modal-paso">
                    <span class="paso-num">2</span><span>Registras el pago desde «Mis reservas»</span>
                </div>
                <div class="modal-paso">
                    <span class="paso-num">3</span><span>Verificamos el pago y confirmamos la reserva</span>
                </div>
            </div>

            <form method="POST" action="{{ route('reservas.store') }}">
                @csrf
                <input type="hidden" name="inmueble_id" value="{{ $inmueble->id }}">

                @if ($inmueble->modalidad === \App\Enumerados\ModalidadInmueble::Ambos)
                    <div class="modal-field">
                        <label>Modalidad a reservar <span class="req">*</span></label>
                        <div class="modal-modalidad-opciones">
                            <label class="modal-modalidad-opcion">
                                <input type="radio" name="modalidad"
                                    value="{{ \App\Enumerados\ModalidadInmueble::Venta->value }}" required>
                                <span>Venta — ${{ number_format((float) $inmueble->precio_venta, 0, ',', '.') }}</span>
                            </label>
                            <label class="modal-modalidad-opcion">
                                <input type="radio" name="modalidad"
                                    value="{{ \App\Enumerados\ModalidadInmueble::Arriendo->value }}" required>
                                <span>Arriendo —
                                    ${{ number_format((float) $inmueble->precio_arrendamiento, 0, ',', '.') }} / mes</span>
                            </label>
                        </div>
                        <small>El monto es el precio real del inmueble según la modalidad que elijas.</small>
                    </div>
                @else
                    <div class="modal-field">
                        <label>Monto de la reserva</label>
                        <p class="modal-monto-fijo">{{ $inmueble->precios[0]['valor'] }}</p>
                        <small>Este es el precio registrado en el sistema.</small>
                    </div>
                @endif

                <div class="modal-field">
                    <label for="notas_cliente">Notas adicionales <span class="text-opcional-modal">(opcional)</span></label>
                    <textarea id="notas_cliente" name="notas_cliente" rows="3" maxlength="1000"
                        placeholder="Horarios disponibles para visita, condiciones, preguntas...">{{ old('notas_cliente') }}</textarea>
                </div>

                <div class="modal-terminos">
                    <input type="checkbox" id="acepta_terminos" name="acepta_terminos" value="1" required>
                    <label for="acepta_terminos">
                        Entiendo que dispongo de <strong>{{ \App\Models\Reserva::HORAS_PARA_PAGAR }} horas</strong> para
                        registrar el pago. Si no lo hago, la reserva expirará y el inmueble quedará disponible de nuevo.
                    </label>
                </div>

                <button type="submit" class="btn-confirmar-reserva">
                    <x-icon name="lock" class="h-5 w-5" /> Confirmar solicitud
                </button>
            </form>
        </div>
    </div>
</div>
