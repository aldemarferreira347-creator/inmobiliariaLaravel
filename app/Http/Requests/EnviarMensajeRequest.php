<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-13.2: envío de un mensaje dentro de un hilo, con adjunto opcional
class EnviarMensajeRequest extends FormRequest
{
    private const MAXIMO_KB_ADJUNTO = 5120;

    public function authorize(): bool
    {
        return $this->user()?->can('responder', $this->route('conversacion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'contenido' => ['nullable', 'string', 'max:2000'],
            'adjunto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::MAXIMO_KB_ADJUNTO],
        ];
    }

    public function messages(): array
    {
        return [
            'adjunto.max' => 'La imagen no puede superar los 5 MB.',
            'adjunto.mimes' => 'Formato no admitido: usa JPG, PNG o WebP.',
        ];
    }
}
