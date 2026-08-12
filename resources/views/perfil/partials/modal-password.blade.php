{{-- Cambio de contraseña como modal del perfil: nunca se navega a una vista aparte --}}
@php($conErrores = $errors->hasAny(['contrasena_actual', 'contrasena']))

<div class="modal-overlay" id="modal-password" @if ($conErrores) data-modal-abierto @endif>
    <div class="modal-box">
        <div class="modal-box-header">
            <h2><x-icon name="lock" class="h-5 w-5" /> Cambiar contraseña</h2>
            <button type="button" class="modal-box-close" data-modal-cerrar="modal-password" aria-label="Cerrar">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')

            <x-password-field name="contrasena_actual" label="Contraseña actual" />

            <x-password-field name="contrasena" label="Nueva contraseña" autocomplete="new-password">
                <x-politica-password />
            </x-password-field>

            <x-password-field name="contrasena_confirmation" label="Confirmar nueva contraseña"
                autocomplete="new-password" />

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" data-modal-cerrar="modal-password">Cancelar</button>
                <button type="submit" class="btn-panel-primary">
                    <x-icon name="save" class="h-4 w-4" /> Guardar contraseña
                </button>
            </div>
        </form>
    </div>
</div>
