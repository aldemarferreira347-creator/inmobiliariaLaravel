{{--
    Alta de usuario con rol (HU-16.1 / HU-26.2).
    Si la validación falla, el modal se reabre solo gracias a data-modal-abierto.
--}}
@php($conErrores = $errors->any() && old('rol') !== null)

<div class="modal-overlay" id="modal-crear-usuario" @if ($conErrores) data-modal-abierto @endif>
    <div class="modal-box modal-box--lg">
        <div class="modal-box-header">
            <h2>Registrar usuario</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-crear-usuario"
                aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.usuarios.store') }}">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="crear-nombre">Nombre completo <span class="req">*</span></label>
                    <input type="text" id="crear-nombre" name="nombre" value="{{ old('nombre') }}" required
                        maxlength="100">
                    @error('nombre')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="crear-email">Correo electrónico <span class="req">*</span></label>
                    <input type="email" id="crear-email" name="email" value="{{ old('email') }}" required
                        maxlength="150">
                    @error('email')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="crear-rol">Rol <span class="req">*</span></label>
                    <select id="crear-rol" name="rol" required>
                        @foreach (\App\Enumerados\RolUsuario::cases() as $rol)
                            <option value="{{ $rol->value }}" @selected(old('rol') === $rol->value)>
                                {{ $rol->etiqueta() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="crear-telefono">Teléfono <span class="text-opcional">(opcional)</span></label>
                    <input type="tel" id="crear-telefono" name="telefono" value="{{ old('telefono') }}"
                        maxlength="20">
                    @error('telefono')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="crear-doc-tipo">Tipo de documento <span class="text-opcional">(opcional)</span></label>
                    <select id="crear-doc-tipo" name="documento_tipo">
                        <option value="">Sin especificar</option>
                        @foreach (['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'PA' => 'Pasaporte'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('documento_tipo') === $valor)>
                                {{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="crear-doc-numero">Número de documento
                        <span class="text-opcional">(opcional)</span></label>
                    <input type="text" id="crear-doc-numero" name="documento_numero"
                        value="{{ old('documento_numero') }}" maxlength="30">
                    @error('documento_numero')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label for="crear-contrasena">Contraseña <span class="req">*</span></label>
                    <input type="password" id="crear-contrasena" name="contrasena" autocomplete="new-password"
                        required>
                    @error('contrasena')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                    <x-politica-password />
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" data-modal-cerrar="modal-crear-usuario">Cancelar</button>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="save" class="h-4 w-4" /> Crear usuario
                </button>
            </div>
        </form>
    </div>
</div>
