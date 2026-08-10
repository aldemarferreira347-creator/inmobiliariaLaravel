@extends('layouts.app')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
    <div class="page-hero">
        <span class="page-hero-badge"><x-icon name="lock" class="h-3.5 w-3.5" /> Seguridad</span>
        <h1>Cambiar contraseña</h1>
        <p>Actualiza la contraseña con la que accedes a tu cuenta</p>
    </div>

    <div class="password-module-container">
        <a href="{{ route('perfil.edit') }}" class="back-link">
            <x-icon name="arrow-left" class="h-4 w-4" /> Volver al perfil
        </a>

        <div class="panel-card">
            <x-flash />

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <x-password-field name="contrasena_actual" label="Contraseña actual" />

                <x-password-field name="contrasena" label="Nueva contraseña" autocomplete="new-password">
                    <x-politica-password />
                </x-password-field>

                <x-password-field name="contrasena_confirmation" label="Confirmar nueva contraseña"
                    autocomplete="new-password" />

                <button type="submit" class="btn-panel-primary form-submit-mt">
                    <x-icon name="save" class="h-4 w-4" /> Guardar contraseña
                </button>
            </form>
        </div>
    </div>
@endsection
