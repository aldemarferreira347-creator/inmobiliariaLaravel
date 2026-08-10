@extends('layouts.auth')

@section('titulo', 'Recuperar contraseña')

@section('formulario')
    <h1>¿Olvidaste tu contraseña?</h1>
    <p class="auth-subtitle">
        Indícanos tu correo y te enviaremos un enlace para crear una nueva. El enlace caduca en 60 minutos.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required
                autofocus>
        </div>

        <button type="submit" class="auth-submit">
            <x-icon name="send" class="h-4 w-4" /> Enviar enlace
        </button>
    </form>

    <p class="auth-footer-link">
        <a href="{{ route('login') }}">Volver a iniciar sesión</a>
    </p>
@endsection
