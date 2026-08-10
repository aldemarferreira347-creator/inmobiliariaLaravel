@extends('layouts.panel')

@section('titulo', 'Gestión de inmuebles')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Gestión de inmuebles</h1>
            <p class="subtitle">Administra todos los inmuebles registrados en el sistema.</p>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card-panel">
            <div class="stat-icon"><x-icon name="building" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $inmuebles->count() }}</div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon stat-icon--success"><x-icon name="circle-check" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoInmueble::Disponible->value] ?? 0 }}</div>
            <div class="stat-label">Disponibles</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon stat-icon--gold"><x-icon name="lock" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoInmueble::Reservado->value] ?? 0 }}</div>
            <div class="stat-label">Reservados</div>
        </div>
        <div class="stat-card-panel">
            <div class="stat-icon"><x-icon name="thumbs-up" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $totalesPorEstado[\App\Enumerados\EstadoInmueble::Ocupado->value] ?? 0 }}</div>
            <div class="stat-label">Ocupados</div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-card-header panel-card-header--between">
            <h2>Listado de inmuebles</h2>
            <button type="button" class="btn-panel-primary" data-modal-abrir="modal-inmueble">
                <x-icon name="plus" class="h-4 w-4" /> Registrar inmueble
            </button>
        </div>

        @if ($inmuebles->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="building" class="h-7 w-7" /></div>
                <p>No hay inmuebles registrados aún.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="inmuebles">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th data-no-sort>Foto</th>
                            <th>Código</th>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Modalidad</th>
                            <th>Precio</th>
                            <th>Hab.</th>
                            <th data-no-sort data-no-export-col>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inmuebles as $inmueble)
                            <tr>
                                <td class="td-id">#{{ $inmueble->id }}</td>
                                <td>
                                    <img class="table-img" src="{{ $inmueble->imagen_url }}" alt="" aria-hidden="true">
                                </td>
                                <td><code class="code-badge">{{ $inmueble->codigo }}</code></td>
                                <td>
                                    <span class="td-title">{{ $inmueble->titulo }}</span>
                                    <span class="td-subtitle">{{ $inmueble->ciudad }}</span>
                                </td>
                                <td><x-estado-badge :estado="$inmueble->estado" base="badge-estado" /></td>
                                <td class="td-capitalize">{{ $inmueble->modalidad->etiqueta() }}</td>
                                <td class="td-price">{{ $inmueble->precios[0]['valor'] }}</td>
                                <td class="td-center-text">{{ $inmueble->habitaciones }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.inmuebles.show', $inmueble) }}"
                                            class="btn-icon btn-icon--info" title="Ver detalle">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </a>

                                        <button type="button" data-modal-abrir="modal-editar-{{ $inmueble->id }}"
                                            class="btn-icon btn-icon--edit" title="Editar">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                        </button>

                                        <form method="POST" action="{{ route('admin.inmuebles.destroy', $inmueble) }}"
                                            data-confirmar data-confirmar-titulo="¿Eliminar inmueble?"
                                            data-confirmar-texto="Se borrarán también sus imágenes. Esta acción no se puede deshacer."
                                            data-confirmar-boton="Sí, eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-icon--danger" title="Eliminar">
                                                <x-icon name="trash-2" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.inmuebles.partials.modal-crear')

    @foreach ($inmuebles as $inmueble)
        @include('admin.inmuebles.partials.modal-editar', ['inmueble' => $inmueble])
    @endforeach
@endsection
