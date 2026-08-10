<?php

namespace App\Servicios;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Almacenamiento de la foto de perfil.
 *
 * Aísla del controlador todo el acceso al sistema de archivos: guardar la nueva
 * imagen, retirar la anterior y dejar el usuario sin foto propia.
 */
class AvatarService
{
    private const DIRECTORIO = 'avatares';

    // Guarda la nueva foto, descarta la anterior y devuelve la ruta pública
    public function reemplazar(User $usuario, UploadedFile $archivo): string
    {
        $ruta = $archivo->store(self::DIRECTORIO, 'public');

        $this->eliminarArchivo($usuario);
        $usuario->update(['foto_url' => 'storage/'.$ruta]);

        return $usuario->foto_url;
    }

    // Deja al usuario con el avatar generado a partir de sus iniciales
    public function eliminar(User $usuario): void
    {
        $this->eliminarArchivo($usuario);
        $usuario->update(['foto_url' => null]);
    }

    private function eliminarArchivo(User $usuario): void
    {
        if (! $usuario->tieneFotoPropia()) {
            return;
        }

        Storage::disk('public')->delete(str_replace('storage/', '', $usuario->foto_url));
    }
}
