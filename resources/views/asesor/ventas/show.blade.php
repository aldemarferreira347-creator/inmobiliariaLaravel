@extends('layouts.panel')

@section('titulo', 'Venta de '.$venta->inmueble->titulo)

@section('panel')
    <a href="{{ route('asesor.ventas.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver al listado
    </a>

    <div class="panel-topbar">
        <div>
            <h1>{{ $venta->inmueble->titulo }}</h1>
            <p class="subtitle">{{ $venta->inmueble->codigo }} · {{ $venta->cliente->nombre }}</p>
        </div>

        <span class="badge {{ $venta->estado->claseBadge() }}">{{ $venta->estado->etiqueta() }}</span>
    </div>

    <div class="admin-rdetalle-grid">
        <div class="admin-rdetalle-left">
            <div class="panel-card">
                <div class="panel-card-header">
                    <h2><x-icon name="dollar-sign" class="h-5 w-5" /> Datos de la venta</h2>
                </div>

                <div class="rdetalle-info-grid">
                    <div class="rinfo-item">
                        <span class="rinfo-label">Precio</span>
                        <span class="rinfo-value rinfo-monto">{{ $venta->precio_formateado }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Fecha</span>
                        <span class="rinfo-value">{{ $venta->fecha_venta->format('d/m/Y') }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Notaría</span>
                        <span class="rinfo-value">{{ $venta->notaria ?: '—' }}</span>
                    </div>
                    <div class="rinfo-item">
                        <span class="rinfo-label">Asesor</span>
                        <span class="rinfo-value">{{ $venta->asesor_nombre }}</span>
                    </div>
                    <div class="rinfo-item rinfo-item--full">
                        <span class="rinfo-label">Comprador</span>
                        <span class="rinfo-value">
                            {{ $venta->cliente->nombre }} · {{ $venta->cliente->email }}
                            @if ($venta->cliente->telefono)
                                · {{ $venta->cliente->telefono }}
                            @endif
                        </span>
                    </div>
                    <div class="rinfo-item rinfo-item--full">
                        <span class="rinfo-label">Inmueble</span>
                        <span class="rinfo-value">
                            <a href="{{ route('inmuebles.show', $venta->inmueble) }}" class="btn-text">
                                {{ $venta->inmueble->codigo }} — {{ $venta->inmueble->titulo }}
                            </a>
                        </span>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-card-header">
                    <h2><x-icon name="file-check" class="h-5 w-5" /> Escritura pública</h2>
                </div>

                @if ($venta->tieneEscritura())
                    <p class="doc-cargado">
                        <x-icon name="file-check" class="h-4 w-4" /> Documento cargado.
                        <a href="{{ route('ventas.escritura', $venta) }}" class="btn-text">Descargar</a>
                    </p>
                @else
                    <p class="subtitle">No se ha subido la escritura todavía.</p>
                @endif

                @can('update', $venta)
                    <form method="POST" action="{{ route('asesor.ventas.escritura', $venta) }}"
                        enctype="multipart/form-data" class="form-actions-row form-mt-8">
                        @csrf
                        <input type="file" name="escritura" accept="application/pdf" required>
                        <button type="submit" class="btn-panel-edit">
                            <x-icon name="paperclip" class="h-4 w-4" /> Subir PDF
                        </button>
                    </form>
                    @error('escritura')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                @endcan
            </div>

            @can('update', $venta)
                <div class="panel-card">
                    <div class="panel-card-header">
                        <h2><x-icon name="settings" class="h-5 w-5" /> Actualizar estado</h2>
                    </div>

                    <div class="form-actions-row">
                        <form method="POST" action="{{ route('asesor.ventas.cerrar', $venta) }}" data-confirmar
                            data-confirmar-tono="exito" data-confirmar-titulo="¿Cerrar la venta?"
                            data-confirmar-texto="El inmueble quedará marcado como ocupado."
                            data-confirmar-boton="Sí, cerrar">
                            @csrf
                            <button type="submit" class="btn-panel-success">
                                <x-icon name="circle-check" class="h-4 w-4" /> Marcar cerrada
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('asesor.ventas.cancelar', $venta) }}" class="form-mt-8"
                        data-confirmar data-confirmar-titulo="¿Cancelar esta venta?"
                        data-confirmar-texto="El inmueble vuelve a estar disponible en el catálogo."
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
                            <x-icon name="ban" class="h-4 w-4" /> Cancelar venta
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endsection
