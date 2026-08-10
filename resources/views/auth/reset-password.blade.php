@extends('layouts.auth')

@section('titulo', 'Nueva contraseña')

@section('formulario')
    <h1>Define tu nueva contraseña</h1>
    <p class="auth-subtitle">Elige una contraseña segura que no hayas usado antes.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="username"
                required>
        </div>

        <x-password-field name="contrasena" label="Nueva contraseña" autocomplete="new-password">
            <x-politica-password />
        </x-password-field>

        <x-password-field name="contrasena_confirmation" label="Confirmar contraseña" autocomplete="new-password" />

        <button type="submit" class="auth-submit">
            <x-icon name="key" class="h-4 w-4" /> Guardar contraseña
        </button>
    </form>
@endsection
