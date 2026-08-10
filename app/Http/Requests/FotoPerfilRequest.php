<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-25.4: la foto de perfil admite JPG, PNG o WebP de hasta 2 MB
class FotoPerfilRequest extends FormRequest
{
    private const MAXIMO_KB = 2048;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::MAXIMO_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.max' => 'La imagen no puede superar los 2 MB.',
            'foto.mimes' => 'Formato no admitido: usa JPG, PNG o WebP.',
        ];
    }
}
