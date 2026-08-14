<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Deja constancia de una acción. El autor y la IP los conoce quien hace la
     * petición HTTP (el Controller), no el Model: se reciben ya resueltos.
     */
    public static function registrar(
        string $entidad,
        ?int $entidadId,
        string $accion,
        string $descripcion,
        ?int $usuarioId = null,
        ?string $ip = null,
    ): self {
        return static::create([
            'usuario_id' => $usuarioId,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'ip_origen' => $ip,
        ]);
    }
}
