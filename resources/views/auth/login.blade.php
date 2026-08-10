@extends('layouts.auth')

@section('titulo', 'Iniciar sesión')

@section('formulario')
    <h1>Bienvenido de nuevo</h1>
    <p class="auth-subtitle">Ingresa tus credenciales para acceder a tu cuenta.</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required
                autofocus>
        </div>

        <x-password-field name="password" label="Contraseña" />

        <button type="submit" class="auth-submit">
            <x-icon name="log-in" class="h-4 w-4" /> Iniciar sesión
        </button>
    </form>

    <p class="auth-footer-link">
        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
    </p>

    <p class="auth-footer-link">
        ¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate</a>
    </p>
@endsection
