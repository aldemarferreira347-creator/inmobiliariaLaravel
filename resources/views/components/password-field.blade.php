{{-- Campo de contraseña con botón para mostrar u ocultar el valor --}}
@props(['name', 'label', 'autocomplete' => 'current-password', 'required' => true])

<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>

    <div class="password-input-wrapper">
        <input type="password" id="{{ $name }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}"
            @required($required) {{ $attributes }}>

        <button type="button" class="toggle-password" data-toggle-password aria-label="Mostrar contraseña">
            <x-icon name="eye" class="pw-eye h-4 w-4" />
            <x-icon name="eye-off" class="pw-eye-off h-4 w-4" />
        </button>
    </div>

    @error($name)
        <span class="error-text">{{ $message }}</span>
    @enderror

    {{ $slot }}
</div>
