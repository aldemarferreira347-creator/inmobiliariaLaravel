@extends('layouts.panel')

@section('titulo', 'Enviar notificación')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Enviar notificación</h1>
            <p class="subtitle">Envía un aviso del sistema a un usuario, a un rol completo o a todo el padrón.</p>
        </div>
    </div>

    <div class="panel-card panel-form-card">
        <form method="POST" action="{{ route('admin.notificaciones.store') }}" data-confirmar
            data-confirmar-titulo="¿Enviar esta notificación?"
            data-confirmar-texto="Revisa bien los destinatarios y el mensaje: se enviará de inmediato y no se puede deshacer."
            data-confirmar-boton="Sí, enviar">
            @csrf

            <div class="form-grid">
                <div class="form-group full">
                    <label for="destino">Destinatarios <span class="req">*</span></label>
                    <select id="destino" name="destino" required data-destino-notificacion>
                        <option value="usuario" @selected(old('destino', 'usuario') === 'usuario')>Un usuario concreto</option>
                        <option value="rol" @selected(old('destino') === 'rol')>Todos los de un rol</option>
                        <option value="todos" @selected(old('destino') === 'todos')>Todos los usuarios</option>
                    </select>
                </div>

                <div class="form-group full" data-destino="usuario">
                    <label for="usuario_id">Usuario</label>
                    <select id="usuario_id" name="usuario_id">
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected(old('usuario_id') == $usuario->id)>
                                {{ $usuario->nombre }} — {{ $usuario->email }} ({{ $usuario->rol->etiqueta() }})
                            </option>
                        @endforeach
                    </select>
                    @error('usuario_id')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full hidden" data-destino="rol">
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol">
                        @foreach (\App\Enumerados\RolUsuario::cases() as $rol)
                            <option value="{{ $rol->value }}" @selected(old('rol') === $rol->value)>
                                {{ $rol->etiquetaPlural() }}
                            </option>
                        @endforeach
                    </select>
                    @error('rol')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label for="titulo">Título <span class="req">*</span></label>
                    <input type="text" id="titulo" name="titulo" value="{{ old('titulo') }}" maxlength="255" required>
                    @error('titulo')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label for="mensaje">Mensaje <span class="req">*</span></label>
                    <textarea id="mensaje" name="mensaje" rows="4" maxlength="2000" required>{{ old('mensaje') }}</textarea>
                    @error('mensaje')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label class="modal-terminos">
                        <input type="checkbox" name="enviar_correo" value="1" @checked(old('enviar_correo'))>
                        <span>Enviar también por correo electrónico</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-panel-primary form-submit-mt">
                <x-icon name="megaphone" class="h-4 w-4" /> Enviar notificación
            </button>
        </form>
    </div>
@endsection
