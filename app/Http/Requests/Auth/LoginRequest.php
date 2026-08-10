<?php

namespace App\Http\Requests\Auth;

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

    /**
     * Verifica las credenciales del formulario.
     *
     * El mensaje de error es deliberadamente genérico para no revelar si el
     * correo existe (OWASP A07). La cuenta desactivada sí se informa, porque
     * ahí el usuario necesita saber a qué atenerse.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->asegurarQueNoEstaBloqueado();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->claveDeBloqueo());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if (! Auth::user()->estaActivo()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está desactivada. Contacta al administrador.',
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
