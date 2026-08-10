<?php

namespace App\Http\Requests\Admin;

use App\Enumerados\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HU-22.1: notificación del sistema enviada por el administrador.
 * El destino puede ser un usuario concreto, un rol completo o todo el padrón.
 */
class EnviarNotificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'destino' => ['required', Rule::in(['usuario', 'rol', 'todos'])],
            'usuario_id' => ['required_if:destino,usuario', 'nullable', 'exists:usuario,id'],
            'rol' => ['required_if:destino,rol', 'nullable', Rule::enum(RolUsuario::class)],
            'titulo' => ['required', 'string', 'max:255'],
            'mensaje' => ['required', 'string', 'max:2000'],
            'enviar_correo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.required_if' => 'Elige el usuario que recibirá la notificación.',
            'rol.required_if' => 'Elige el rol que recibirá la notificación.',
        ];
    }
}
