@extends('layouts.panel')

@section('titulo', 'Contrato '.$contrato->numero_contrato)

@section('panel')
    <a href="{{ route('admin.contratos.index') }}" class="back-link">
        <x-icon name="arrow-left" class="h-4 w-4" /> Volver al listado
    </a>

    <div class="panel-topbar">
        <div>
            <h1>Contrato {{ $contrato->numero_contrato }}</h1>
            <p class="subtitle">{{ $contrato->reserva->inmueble->titulo }} ·
                {{ $contrato->reserva->cliente->nombre }}</p>
        </div>

        <span class="badge {{ $contrato->estado->claseBadge() }}">{{ $contrato->estado->etiqueta() }}</span>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="file-text" class="h-5 w-5" /> Datos del contrato</h2>
        </div>

        <div class="perfil-details">
            <div class="detail-item">
                <span class="rinfo-label">Vigencia</span>
                <span class="rinfo-value">{{ $contrato->vigencia }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Valor mensual</span>
                <span class="rinfo-value rinfo-monto">{{ $contrato->valor_formateado }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Reserva</span>
                <span class="rinfo-value">
                    <a href="{{ route('admin.reservas.show', $contrato->reserva) }}" class="btn-text">
                        {{ $contrato->reserva->codigo_reserva }}
                    </a>
                </span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Inmueble</span>
                <span class="rinfo-value">
                    <a href="{{ route('admin.inmuebles.show', $contrato->reserva->inmueble) }}" class="btn-text">
                        {{ $contrato->reserva->inmueble->codigo }}
                    </a>
                </span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Cliente</span>
                <span class="rinfo-value">{{ $contrato->reserva->cliente->nombre }} ·
                    {{ $contrato->reserva->cliente->email }}</span>
            </div>
            <div class="detail-item">
                <span class="rinfo-label">Emitido</span>
                <span class="rinfo-value">{{ $contrato->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header">
            <h2><x-icon name="file-check" class="h-5 w-5" /> Contrato firmado</h2>
        </div>

        @if ($contrato->tieneArchivo())
            <p class="doc-cargado">
                <x-icon name="circle-check" class="h-4 w-4" /> El documento firmado ya está cargado.
            </p>
            <a href="{{ route('contratos.descargar', $contrato) }}" class="btn-panel-edit form-submit-mt">
                <x-icon name="download" class="h-4 w-4" /> Descargar PDF
            </a>
        @else
            <p class="subtitle">Todavía no se ha adjuntado el contrato firmado.</p>
        @endif

        <form method="POST" action="{{ route('admin.contratos.documento', $contrato) }}"
            enctype="multipart/form-data" class="form-mt-8">
            @csrf

            <div class="form-group">
                <label for="documento">
                    {{ $contrato->tieneArchivo() ? 'Reemplazar el documento' : 'Subir el contrato firmado' }}
                    <span class="text-opcional">(PDF · máx. 5 MB)</span>
                </label>
                <input type="file" id="documento" name="documento" accept="application/pdf"
                    class="input-file-custom" required>
                @error('documento')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn-upload-submit">
                <x-icon name="upload" class="h-4 w-4" /> Guardar documento
            </button>
        </form>
    </div>

    @can('rescindir', $contrato)
        <div class="panel-card danger-zone">
            <h3 class="danger-zone-title">
                <x-icon name="triangle-alert" class="h-4 w-4" /> Rescindir el contrato
            </h3>

            <form method="POST" action="{{ route('admin.contratos.rescindir', $contrato) }}" data-confirmar
                data-confirmar-titulo="¿Rescindir este contrato?"
                data-confirmar-texto="El inmueble volverá a estar disponible y se avisará al cliente."
                data-confirmar-boton="Sí, rescindir">
                @csrf

                <div class="form-group">
                    <label for="motivo">Motivo <span class="req">*</span></label>
                    <input type="text" id="motivo" name="motivo" maxlength="255" required>
                    @error('motivo')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn-panel-danger">
                    <x-icon name="ban" class="h-4 w-4" /> Rescindir contrato
                </button>
            </form>
        </div>
    @endcan
@endsection
