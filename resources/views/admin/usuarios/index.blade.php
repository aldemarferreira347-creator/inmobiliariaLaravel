@extends('layouts.panel')

@section('titulo', 'Gestión de usuarios')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Gestión de usuarios</h1>
            <p class="subtitle">Administra las cuentas, sus roles y su estado de acceso.</p>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card-panel">
            <div class="stat-icon"><x-icon name="users" class="h-5 w-5" /></div>
            <div class="stat-value">{{ $usuarios->count() }}</div>
            <div class="stat-label">Total</div>
        </div>
        @foreach (\App\Enumerados\RolUsuario::cases() as $rol)
            <div class="stat-card-panel">
                <div class="stat-icon stat-icon--{{ $rol === \App\Enumerados\RolUsuario::Administrador ? 'danger' : ($rol === \App\Enumerados\RolUsuario::Asesor ? 'gold' : 'success') }}">
                    <x-icon name="user" class="h-5 w-5" />
                </div>
                <div class="stat-value">{{ $totalesPorRol[$rol->value] ?? 0 }}</div>
                <div class="stat-label">{{ $rol->etiquetaPlural() }}</div>
            </div>
        @endforeach
    </div>

    <div class="panel-card">
        <div class="panel-card-header panel-card-header--between">
            <h2>Listado de usuarios</h2>
            <button type="button" class="btn-panel-primary" data-modal-abrir="modal-crear-usuario">
                <x-icon name="user-plus" class="h-4 w-4" /> Registrar usuario
            </button>
        </div>

        @if ($usuarios->isEmpty())
            <div class="empty-panel">
                <div class="empty-icon"><x-icon name="users" class="h-7 w-7" /></div>
                <p>No hay usuarios registrados aún.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="panel-table" data-enhance-table data-export-name="usuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Documento</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th data-no-sort data-no-export-col>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            @php($esPropia = $usuario->esElMismoQue(auth()->user()))
                            <tr>
                                <td class="td-id">#{{ $usuario->id }}</td>
                                <td>
                                    <span class="td-title">{{ $usuario->nombre }}</span>
                                    <span class="td-subtitle">{{ $usuario->ciudad ?: 'Sin ciudad' }}</span>
                                </td>
                                <td class="td-email" title="{{ $usuario->email }}">{{ $usuario->email }}</td>
                                <td class="td-phone">{{ $usuario->telefono ?: '—' }}</td>
                                <td>{{ $usuario->documento_numero ?: '—' }}</td>
                                <td>
                                    @if ($esPropia)
                                        {{-- El administrador no puede cambiarse el rol a sí mismo --}}
                                        <span class="badge {{ $usuario->rol->claseBadge() }}">
                                            {{ $usuario->rol->etiqueta() }}
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.usuarios.rol', $usuario) }}"
                                            data-confirmar data-confirmar-titulo="¿Cambiar el rol de {{ $usuario->nombre }}?"
                                            data-confirmar-boton="Sí, cambiar rol">
                                            @csrf
                                            @method('PATCH')
                                            <select name="rol" class="role-select-inline"
                                                data-valor-original="{{ $usuario->rol->value }}"
                                                onchange="this.form.dataset.confirmarTexto = 'Tendrá de inmediato los permisos de ' + this.options[this.selectedIndex].text + '.'; this.form.requestSubmit()"
                                                aria-label="Rol de {{ $usuario->nombre }}">
                                                @foreach (\App\Enumerados\RolUsuario::cases() as $rol)
                                                    <option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>
                                                        {{ $rol->etiqueta() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    <span
                                        class="badge {{ $usuario->estaActivo() ? 'badge-confirmada' : 'badge-cancelada' }}">
                                        {{ $usuario->estado->etiqueta() }}
                                    </span>
                                </td>
                                <td class="td-date">{{ $usuario->creado_en->format('d/m/Y') }}</td>
                                <td>
                                    <div class="row-actions">
                                        @unless ($esPropia)
                                            <form method="POST"
                                                action="{{ route('admin.usuarios.estado', $usuario) }}" data-confirmar
                                                data-confirmar-tono="{{ $usuario->estaActivo() ? 'aviso' : 'exito' }}"
                                                data-confirmar-titulo="{{ $usuario->estaActivo() ? '¿Desactivar usuario?' : '¿Activar usuario?' }}"
                                                data-confirmar-texto="{{ $usuario->estaActivo() ? 'El usuario no podrá iniciar sesión hasta reactivarlo.' : 'El usuario podrá volver a iniciar sesión.' }}"
                                                data-confirmar-boton="{{ $usuario->estaActivo() ? 'Sí, desactivar' : 'Sí, activar' }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="btn-icon {{ $usuario->estaActivo() ? 'btn-icon--warning' : 'btn-icon--success' }}"
                                                    title="{{ $usuario->estaActivo() ? 'Desactivar' : 'Activar' }}">
                                                    <x-icon :name="$usuario->estaActivo() ? 'ban' : 'circle-check'"
                                                        class="h-4 w-4" />
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                                data-confirmar data-confirmar-titulo="¿Eliminar usuario?"
                                                data-confirmar-texto="Esta acción es permanente y no se puede deshacer."
                                                data-confirmar-boton="Sí, eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon btn-icon--danger" title="Eliminar">
                                                    <x-icon name="trash-2" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @else
                                            <span class="td-muted" title="Tu propia cuenta">
                                                <x-icon name="lock" class="h-4 w-4" />
                                            </span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.usuarios.partials.modal-crear')
@endsection
