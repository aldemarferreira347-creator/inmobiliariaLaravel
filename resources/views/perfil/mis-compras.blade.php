@extends('layouts.app')

@section('titulo', 'Mis compras')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="tag" class="h-3.5 w-3.5" /> Mi cuenta</span>
        <h1>Mis compras</h1>
        <p>Tus procesos de compra y su documentación</p>
    </div>

    <section class="section-perfil">
        <div class="container">
            <x-flash />

            <div class="perfil-dashboard">
                @include('perfil.partials.sidebar')

                <main class="perfil-content">
                    @if ($ventas->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-icon"><x-icon name="tag" class="h-10 w-10" /></div>
                            <p class="empty-state-title">Todavía no tienes compras</p>
                            <p class="empty-state-sub">Cuando inicies un proceso de compra con un asesor, lo verás aquí.</p>
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
                                        <th>Asesor</th>
                                        <th>Precio</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th data-no-sort>Escritura</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ventas as $venta)
                                        <tr>
                                            <td>
                                                <span class="td-title">{{ $venta->inmueble->titulo }}</span>
                                                <span class="td-subtitle">{{ $venta->inmueble->ubicacion }}</span>
                                            </td>
                                            <td>{{ $venta->asesor_nombre }}</td>
                                            <td class="td-price">{{ $venta->precio_formateado }}</td>
                                            <td class="td-date">{{ $venta->fecha_venta->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge {{ $venta->estado->claseBadge() }}">
                                                    {{ $venta->estado->etiqueta() }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($venta->tieneEscritura())
                                                    <a href="{{ route('ventas.escritura', $venta) }}"
                                                        class="btn-icon btn-icon--info" title="Descargar escritura">
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
                </main>
            </div>
        </div>
    </section>
@endsection
