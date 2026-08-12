{{-- Mis arriendos como modal del perfil: nunca se navega a una vista aparte --}}
<div class="modal-overlay" id="modal-arriendos">
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2><x-icon name="key" class="h-5 w-5" /> Mis arriendos</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-arriendos" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        @if ($reservas->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon"><x-icon name="key" class="h-10 w-10" /></div>
                <p class="empty-state-title">Todavía no tienes arriendos</p>
                <p class="empty-state-sub">Cuando confirmemos una reserva de arriendo, la verás aquí.</p>
                <a href="{{ route('inmuebles.index') }}" class="btn-primary btn-all-properties">
                    Explorar catálogo
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table">
                    <thead>
                        <tr>
                            <th>Inmueble</th>
                            <th>Contrato</th>
                            <th>Valor mensual</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                            <th data-no-sort>Documento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservas as $reserva)
                            @php($contrato = $reserva->contrato)
                            <tr>
                                <td>
                                    <span class="td-title">{{ $reserva->inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $reserva->inmueble->ubicacion }}</span>
                                </td>
                                <td>
                                    <code class="code-badge">
                                        {{ $contrato?->numero_contrato ?? '—' }}
                                    </code>
                                </td>
                                <td class="td-price">
                                    {{ $contrato?->valor_formateado ?? $reserva->monto_formateado }}
                                </td>
                                <td class="td-nowrap">{{ $contrato?->vigencia ?? '—' }}</td>
                                <td>
                                    @if ($contrato)
                                        <span class="badge {{ $contrato->estado->claseBadge() }}">
                                            {{ $contrato->estado->etiqueta() }}
                                        </span>
                                    @else
                                        <span class="badge badge-pendiente-conf">Contrato pendiente</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($contrato && $contrato->tieneArchivo())
                                        <a href="{{ route('contratos.descargar', $contrato) }}"
                                            class="btn-icon btn-icon--info" title="Descargar contrato">
                                            <x-icon name="download" class="h-4 w-4" />
                                        </a>
                                    @else
                                        <span class="td-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
