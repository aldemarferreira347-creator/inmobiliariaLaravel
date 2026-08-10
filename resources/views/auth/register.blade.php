@extends('layouts.auth', ['ancha' => true])

@section('titulo', 'Crear cuenta')

@section('formulario')
    <h1>Crea tu cuenta</h1>
    <p class="auth-subtitle">Regístrate para guardar favoritos y contactar con nuestros asesores.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="auth-row">
            <div class="auth-field">
                <label for="nombre">Nombre completo <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" autocomplete="name" required
                    autofocus>
                @error('nombre')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-field">
                <label for="email">Correo electrónico <span class="req">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
                @error('email')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="auth-row">
            <div class="auth-field">
                <label for="documento_tipo">Tipo de documento <span class="req">*</span></label>
                <select id="documento_tipo" name="documento_tipo" required>
                    @foreach (['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PA' => 'Pasaporte'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('documento_tipo') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            <div class="auth-field">
                <label for="documento_numero">Número de documento <span class="req">*</span></label>
                <input type="text" id="documento_numero" name="documento_numero" value="{{ old('documento_numero') }}"
                    required>
                @error('documento_numero')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="auth-row">
            <div class="auth-field">
                <label for="telefono">Teléfono <span class="text-opcional">(opcional)</span></label>
                <input type="tel" id="telefono" name="telefono" value="{{ old('telefono') }}" autocomplete="tel">
                @error('telefono')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-field">
                <label for="fecha_nacimiento">Fecha de nacimiento <span class="text-opcional">(opcional)</span></label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}">
            </div>
        </div>

        <div class="auth-row">
            <div class="auth-field">
                <label for="ciudad">Ciudad <span class="text-opcional">(opcional)</span></label>
                <input type="text" id="ciudad" name="ciudad" value="{{ old('ciudad') }}">
            </div>

            <div class="auth-field">
                <label for="direccion">Dirección <span class="text-opcional">(opcional)</span></label>
                <input type="text" id="direccion" name="direccion" value="{{ old('direccion') }}">
            </div>
        </div>

        <div class="auth-row">
            <x-password-field name="contrasena" label="Contraseña" autocomplete="new-password">
                <x-politica-password />
            </x-password-field>

            <x-password-field name="contrasena_confirmation" label="Confirmar contraseña"
                autocomplete="new-password" />
        </div>

        <button type="submit" class="auth-submit">
            <x-icon name="user-plus" class="h-4 w-4" /> Crear cuenta
        </button>
    </form>

    <p class="auth-footer-link">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>
    </p>
@endsection
