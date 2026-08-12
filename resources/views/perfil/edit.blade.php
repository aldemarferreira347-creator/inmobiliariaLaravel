@extends('layouts.app')

@section('titulo', 'Mi perfil')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="user" class="h-3.5 w-3.5" /> Mi cuenta</span>
        <h1>Mi perfil</h1>
        <p>Gestiona tu información personal y tu actividad</p>
    </div>

    <section class="section-perfil">
        <div class="container">
            <x-flash />

            <div class="perfil-dashboard">
                @include('perfil.partials.sidebar')

                <main class="perfil-content">
                    <div class="perfil-stats">
                        <div class="stat-card">
                            <h3>{{ $totalFavoritos }}</h3>
                            <p>Favoritos</p>
                        </div>
                        <div class="stat-card">
                            <h3>{{ $usuario->creado_en->format('Y') }}</h3>
                            <p>Miembro desde</p>
                        </div>
                        <div class="stat-card">
                            <h3>{{ $usuario->rol->etiqueta() }}</h3>
                            <p>Rol</p>
                        </div>
                    </div>

                    {{-- Información personal: se alterna entre lectura y edición --}}
                    <div class="section-perfil perfil-details-mb">
                        <div class="details-header">
                            <h3><x-icon name="user" class="h-5 w-5" /> Información personal</h3>
                            <div class="flex-row-center-gap10">
                                <button type="button" class="btn-panel-edit" data-modal-abrir="modal-password">
                                    <x-icon name="lock" class="h-4 w-4" /> Cambiar contraseña
                                </button>
                                <button type="button" class="btn-panel-edit" data-perfil-editar>
                                    <x-icon name="pencil" class="h-4 w-4" /> Editar
                                </button>
                            </div>
                        </div>

                        <div id="vista-lectura" class="perfil-details">
                            <div class="detail-item">
                                <span class="rinfo-label">Nombre completo</span>
                                <span class="rinfo-value">{{ $usuario->nombre }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Correo electrónico</span>
                                <span class="rinfo-value">
                                    {{ $usuario->email }}
                                    <span class="field-lock-badge" title="No editable por seguridad">
                                        <x-icon name="lock" class="h-3 w-3" />
                                    </span>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Teléfono</span>
                                <span class="rinfo-value">{{ $usuario->telefono ?: '—' }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Fecha de nacimiento</span>
                                <span class="rinfo-value">
                                    {{ $usuario->fecha_nacimiento?->format('d/m/Y') ?? '—' }}
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Ciudad</span>
                                <span class="rinfo-value">{{ $usuario->ciudad ?: '—' }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Dirección</span>
                                <span class="rinfo-value">{{ $usuario->direccion ?: '—' }}</span>
                            </div>
                        </div>

                        <form id="form-editar" method="POST" action="{{ route('perfil.update') }}" class="hidden"
                            data-abierto="{{ $errors->hasAny(['nombre', 'telefono', 'fecha_nacimiento', 'ciudad', 'direccion']) ? 'si' : 'no' }}">
                            @csrf
                            @method('PATCH')

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="nombre">Nombre completo <span class="req">*</span></label>
                                    <input type="text" id="nombre" name="nombre"
                                        value="{{ old('nombre', $usuario->nombre) }}" required maxlength="100">
                                    @error('nombre')
                                        <span class="error-text">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="email-readonly">Correo electrónico</label>
                                    <input type="email" id="email-readonly" value="{{ $usuario->email }}"
                                        class="input-readonly" disabled>
                                    <small class="field-note">
                                        <x-icon name="lock" class="h-3 w-3" /> Para cambiar el correo contacta a soporte.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="tel" id="telefono" name="telefono"
                                        value="{{ old('telefono', $usuario->telefono) }}" placeholder="Ej: 300 000 0000"
                                        maxlength="20">
                                    @error('telefono')
                                        <span class="error-text">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de nacimiento</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                                        value="{{ old('fecha_nacimiento', $usuario->fecha_nacimiento?->format('Y-m-d')) }}"
                                        max="{{ now()->subYears(16)->format('Y-m-d') }}">
                                    @error('fecha_nacimiento')
                                        <span class="error-text">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <p class="perfil-subtitle"><x-icon name="map-pin" class="h-4 w-4" /> Dirección</p>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="ciudad">Ciudad</label>
                                    <input type="text" id="ciudad" name="ciudad"
                                        value="{{ old('ciudad', $usuario->ciudad) }}" placeholder="Ej: Neiva"
                                        maxlength="100">
                                </div>

                                <div class="form-group">
                                    <label for="direccion">Dirección</label>
                                    <input type="text" id="direccion" name="direccion"
                                        value="{{ old('direccion', $usuario->direccion) }}" placeholder="Cra 10 # 20-30"
                                        maxlength="255">
                                </div>
                            </div>

                            <div class="form-actions-row">
                                <button type="submit" class="btn-panel-primary">
                                    <x-icon name="save" class="h-4 w-4" /> Guardar cambios
                                </button>
                                <button type="button" class="btn-panel-cancelar" data-perfil-editar>Cancelar</button>
                            </div>
                        </form>
                    </div>

                    {{-- El documento identifica la cuenta: por integridad legal no se edita desde el perfil --}}
                    <div class="section-perfil perfil-details-mb">
                        <div class="details-header">
                            <h3><x-icon name="id-card" class="h-5 w-5" /> Documento de identidad</h3>
                            <span class="field-lock-badge lock-badge-sm">
                                <x-icon name="lock" class="h-3 w-3" /> Solo lectura
                            </span>
                        </div>

                        <div class="perfil-details">
                            <div class="detail-item">
                                <span class="rinfo-label">Tipo de documento</span>
                                <span class="rinfo-value">{{ $usuario->documento_tipo ?: '—' }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="rinfo-label">Número de documento</span>
                                <span class="rinfo-value">{{ $usuario->documento_numero ?: '—' }}</span>
                            </div>
                        </div>

                        <p class="field-note field-note-mt">
                            Por integridad legal, el documento de identidad no puede modificarse desde aquí.
                            Si necesitas corregirlo, contacta a soporte.
                        </p>
                    </div>
                </main>
            </div>
        </div>
    </section>

    {{-- Secciones de la cuenta abiertas como modal: nunca navegan a otra vista --}}
    @include('perfil.partials.modal-password')
    @include('perfil.partials.modal-arriendos')
    @include('perfil.partials.modal-compras')
    @include('perfil.partials.modal-tarjetas')

    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
    @endpush
@endsection
