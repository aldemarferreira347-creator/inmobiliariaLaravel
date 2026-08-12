{{-- Mis tarjetas como modal del perfil: nunca se navega a una vista aparte --}}
<div class="modal-overlay" id="modal-tarjetas" @if (session('reabrirModal') === 'modal-tarjetas') data-modal-abierto @endif>
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2><x-icon name="credit-card" class="h-5 w-5" /> Mis tarjetas</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-tarjetas" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        <h3 class="mb-4">Tarjetas guardadas</h3>

        @if ($tarjetasGuardadas->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><x-icon name="credit-card" class="h-10 w-10" /></div>
                <p class="empty-state-title">Todavía no tienes tarjetas guardadas</p>
                <p class="empty-state-sub">Agrega una abajo para pagar tus reservas en un clic.</p>
            </div>
        @else
            <div class="tarjetas-grid">
                @foreach ($tarjetasGuardadas as $tarjeta)
                    <div class="tarjeta-card">
                        <div class="tarjeta-card-marca">
                            <x-icon name="credit-card" class="h-5 w-5" /> {{ $tarjeta->descripcion }}
                            @if ($tarjeta->predeterminado)
                                <span class="badge badge-confirmada">Predeterminada</span>
                            @endif
                        </div>
                        <span class="tarjeta-card-meta">
                            @if ($tarjeta->nombre_titular)
                                {{ $tarjeta->nombre_titular }} ·
                            @endif
                            Vence {{ str_pad((string) $tarjeta->mes_expiracion, 2, '0', STR_PAD_LEFT) }}/{{ $tarjeta->anio_expiracion }}
                        </span>

                        <form method="POST" action="{{ route('perfil.tarjetas.destroy', $tarjeta) }}"
                            data-confirmar data-confirmar-titulo="¿Eliminar esta tarjeta?"
                            data-confirmar-texto="Ya no podrás usarla para pagar reservas."
                            data-confirmar-boton="Sí, eliminar">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-logout btn-logout-sm">
                                <x-icon name="trash-2" class="h-4 w-4" /> Eliminar
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <h3 class="mb-4 form-mt-8">Agregar una tarjeta nueva</h3>

        <form id="form-tarjeta-nueva" data-stripe-key="{{ $stripeKey }}"
            data-setup-intent-url="{{ route('perfil.tarjetas.setup-intent') }}"
            data-guardar-url="{{ route('perfil.tarjetas.store') }}">
            <div class="form-group">
                <label for="stripe-card-element">Datos de la tarjeta</label>
                <div id="stripe-card-element"></div>
                <span id="stripe-card-errors" role="alert"></span>
            </div>

            <button type="submit" class="btn-panel-primary">
                <x-icon name="credit-card" class="h-4 w-4" /> Guardar tarjeta
            </button>
        </form>
    </div>
</div>
