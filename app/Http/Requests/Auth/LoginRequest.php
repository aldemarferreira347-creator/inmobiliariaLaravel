<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    // Intentos fallidos admitidos antes de bloquear la combinación correo + IP
    private const INTENTOS_MAXIMOS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    // Los correos se guardan en minúsculas: el inicio de sesión no debe depender de cómo se teclee
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * Verifica las credenciales del formulario.
     *
     * Por exigencia de la HU-05.3 se informa cuando el correo no tiene cuenta
     * (riesgo aceptado de enumeración, mitigado con el límite de intentos).
     * Con correo existente y contraseña errónea el mensaje no dice cuál falló.
     * La cuenta inactiva solo se informa con credenciales correctas.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->asegurarQueNoEstaBloqueado();

        if (! User::where('email', $this->string('email')->toString())->exists()) {
            RateLimiter::hit($this->claveDeBloqueo());

            throw ValidationException::withMessages(['email' => trans('auth.sin_cuenta')]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->claveDeBloqueo());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if (! Auth::user()->estaActivo()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => trans('auth.inactiva'),
            ]);
        }

        RateLimiter::clear($this->claveDeBloqueo());
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueNoEstaBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->claveDeBloqueo(), self::INTENTOS_MAXIMOS)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->claveDeBloqueo());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $segundos,
                'minutes' => ceil($segundos / 60),
            ]),
        ]);
    }

    private function claveDeBloqueo(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
