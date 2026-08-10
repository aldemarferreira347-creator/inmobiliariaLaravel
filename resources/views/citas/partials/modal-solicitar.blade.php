{{--
    Solicitud de cita para visitar el inmueble (HU-27.1).
    Las horas disponibles se cargan por AJAX según la fecha elegida, para no
    ofrecer nunca un horario fuera de la franja configurada u ocupado.
--}}
<div class="modal-overlay" id="modal-cita" @if ($errors->hasAny(['inmueble', 'hora_cita', 'fecha_cita'])) data-modal-abierto @endif>
    <div class="modal-reserva-box">
        <div class="modal-reserva-header">
            <h2><x-icon name="calendar" class="h-5 w-5" /> Agendar visita</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-cita" aria-label="Cerrar">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="modal-reserva-body">
            <div class="modal-inmueble-info">
                <img src="{{ $inmueble->imagen_url }}" alt="" aria-hidden="true">
                <div>
                    <strong>{{ $inmueble->titulo }}</strong>
                    <span><x-icon name="map-pin" class="h-3.5 w-3.5" /> {{ $inmueble->ubicacion }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('citas.store') }}" id="form-cita" data-cita-form
                data-franjas-url="{{ route('citas.franjas-disponibles') }}">
                @csrf
                <input type="hidden" name="inmueble_id" value="{{ $inmueble->id }}">

                <div class="modal-field">
                    <label for="fecha_cita">Fecha de la visita <span class="req">*</span></label>
                    <input type="date" id="fecha_cita" name="fecha_cita" required
                        min="{{ now()->toDateString() }}" max="{{ now()->addDays(30)->toDateString() }}"
                        value="{{ old('fecha_cita') }}">
                    @error('fecha_cita')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="modal-field">
                    <label for="hora_cita">Hora <span class="req">*</span></label>
                    <select id="hora_cita" name="hora_cita" required disabled>
                        <option value="">Elige primero una fecha</option>
                    </select>
                    <small data-cita-hint>Lunes a sábado, 08:00 a 18:00.</small>
                    @error('hora_cita')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn-confirmar-reserva">
                    <x-icon name="calendar" class="h-5 w-5" /> Confirmar visita
                </button>
            </form>
        </div>
    </div>
</div>
