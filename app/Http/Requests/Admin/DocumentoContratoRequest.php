<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// HU-17.2: el contrato firmado se sube en PDF y no supera los 5 MB
class DocumentoContratoRequest extends FormRequest
{
    private const MAXIMO_KB = 5120;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contrato')) ?? false;
    }

    public function rules(): array
    {
        return [
            'documento' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.self::MAXIMO_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.mimes' => 'El contrato firmado debe ser un archivo PDF.',
            'documento.mimetypes' => 'El contrato firmado debe ser un archivo PDF.',
            'documento.max' => 'El archivo no puede superar los 5 MB.',
        ];
    }
}
