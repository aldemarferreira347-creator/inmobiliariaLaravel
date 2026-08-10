@extends('layouts.app')

@section('titulo', 'Mis citas')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="calendar" class="h-3.5 w-3.5" /> Mi cuenta</span>
        <h1>Mis citas</h1>
        <p>Visitas que agendaste y su estado</p>
    </div>

    <section class="section-perfil">
        <div class="container">
            <x-flash />

            <div class="perfil-dashboard">
                @include('perfil.partials.sidebar')

                <main class="perfil-content">
                    @if ($citas->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-icon"><x-icon name="calendar" class="h-10 w-10" /></div>
                            <p class="empty-state-title">Todavía no tienes citas agendadas</p>
                            <p class="empty-state-sub">Agenda una visita desde la ficha de cualquier inmueble.</p>
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
                                        <th>Fecha</th>
                                        <th>Asesor</th>
                                        <th>Estado</th>
                                        <th data-no-sort>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($citas as $cita)
                                        <tr>
                                            <td>
                                                <span class="td-title">{{ $cita->inmueble->titulo }}</span>
                                                <span class="td-subtitle">{{ $cita->inmueble->codigo }}</span>
                                            </td>
                                            <td class="td-date">{{ $cita->fecha->format('d/m/Y H:i') }}</td>
                                            <td>{{ $cita->asesor?->nombre ?? 'Sin asignar' }}</td>
                                            <td>
                                                <span class="badge {{ $cita->estado->claseBadge() }}">
                                                    {{ $cita->estado->etiqueta() }}
                                                </span>
                                            </td>
                                            <td>
                                                @can('cancelar', $cita)
                                                    <form method="POST" action="{{ route('citas.cancelar', $cita) }}"
                                                        data-confirmar data-confirmar-titulo="¿Cancelar la cita?"
                                                        data-confirmar-texto="Perderás tu horario reservado para esta visita."
                                                        data-confirmar-boton="Sí, cancelar">
                                                        @csrf
                                                        <button type="submit" class="btn-icon btn-icon--danger"
                                                            title="Cancelar cita">
                                                            <x-icon name="x" class="h-4 w-4" />
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="td-muted">—</span>
                                                @endcan
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
