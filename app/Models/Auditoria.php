<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

// Registro de auditoría de acciones administrativas (HU-26)
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = ['usuario_id', 'entidad', 'entidad_id', 'accion', 'descripcion', 'ip_origen'];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Deja constancia de una acción tomando la IP de la petición en curso
    public static function registrar(string $entidad, ?int $entidadId, string $accion, string $descripcion): self
    {
        return static::create([
            'usuario_id' => auth()->id(),
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'ip_origen' => Request::ip(),
        ]);
    }
}
