@extends('layouts.panel')

@section('titulo', 'Ventas')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Ventas</h1>
            <p class="subtitle">Procesos de venta de inmuebles y su documentación.</p>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header panel-card-header--between">
            <h2>Listado de ventas</h2>
            <button type="button" class="btn-panel-primary" data-modal-abrir="modal-venta">
                <x-icon name="plus" class="h-4 w-4" /> Registrar venta
            </button>
        </div>

        @if ($ventas->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="dollar-sign" class="h-7 w-7" /></div>
                <p>No hay registros disponibles.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="ventas">
                    <thead>
                        <tr>
                            <th>Inmueble</th>
                            <th>Cliente</th>
                            <th>Asesor</th>
                            <th>Precio</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th data-no-sort data-no-export-col>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ventas as $venta)
                            <tr>
                                <td>
                                    <span class="td-title">{{ $venta->inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $venta->inmueble->codigo }}</span>
                                </td>
                                <td>{{ $venta->cliente->nombre }}</td>
                                <td>{{ $venta->asesor_nombre }}</td>
                                <td class="td-price">{{ $venta->precio_formateado }}</td>
                                <td class="td-date">{{ $venta->fecha_venta->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge {{ $venta->estado->claseBadge() }}">
                                        {{ $venta->estado->etiqueta() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('asesor.ventas.show', $venta) }}" class="btn-icon btn-icon--info"
                                            title="Ver detalle">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </a>

                                        @can('update', $venta)
                                            <form method="POST" action="{{ route('asesor.ventas.cerrar', $venta) }}"
                                                data-confirmar data-confirmar-tono="exito"
                                                data-confirmar-titulo="¿Cerrar la venta?"
                                                data-confirmar-texto="El inmueble quedará marcado como ocupado."
                                                data-confirmar-boton="Sí, cerrar">
                                                @csrf
                                                <button type="submit" class="btn-icon btn-icon--success"
                                                    title="Cerrar venta">
                                                    <x-icon name="circle-check" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @endcan

                                        @can('descargarEscritura', $venta)
                                            <a href="{{ route('ventas.escritura', $venta) }}" class="btn-icon btn-icon--info"
                                                title="Descargar escritura">
                                                <x-icon name="download" class="h-4 w-4" />
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('asesor.ventas.partials.modal-registrar')
@endsection
