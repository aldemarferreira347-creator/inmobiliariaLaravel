<?php

namespace App\Models;

use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario del sistema (tabla `usuario`).
 *
 * Un único registro cubre los tres roles; el rol se resuelve por `usuario.rol`,
 * FK contra `rol.codigo`. El prototipo tenía además las tablas espejo `cliente`
 * y `asesor`, que no aportaban columnas propias y aquí se eliminaron.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuario';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'email',
        'contrasena',
        'telefono',
        'rol',
        'documento_tipo',
        'documento_numero',
        'fecha_nacimiento',
        'ciudad',
        'direccion',
        'foto_url',
        'estado',
        'desactivado_en',
        'desactivado_por',
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'contrasena' => 'hashed',
            'rol' => RolUsuario::class,
            'estado' => EstadoUsuario::class,
            'fecha_nacimiento' => 'date',
            'desactivado_en' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    // El esquema nombra la columna `contrasena`, no `password`
    public function getAuthPassword(): string
    {
        return $this->contrasena;
    }

    // ----------------------------------------------------------------
    // Relaciones
    // ----------------------------------------------------------------

    public function rolInfo(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol', 'codigo');
    }

    public function favoritos(): BelongsToMany
    {
        return $this->belongsToMany(Inmueble::class, 'favorito', 'usuario_id', 'inmueble_id')
            ->withPivot('creado_en');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'usuario_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'usuario_id');
    }

    public function ventasAsesoradas(): HasMany
    {
        return $this->hasMany(Venta::class, 'asesor_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'cliente_id');
    }

    public function citasAsignadas(): HasMany
    {
        return $this->hasMany(Cita::class, 'asesor_id');
    }

    public function metodosPago(): HasMany
    {
        return $this->hasMany(MetodoPagoGuardado::class, 'cliente_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }

    public function conversacionesComoCliente(): HasMany
    {
        return $this->hasMany(Conversacion::class, 'cliente_id');
    }

    public function conversacionesComoAsesor(): HasMany
    {
        return $this->hasMany(Conversacion::class, 'asesor_id');
    }

    /** Hilos en los que participa, según el lado que le corresponde por su rol */
    public function conversaciones(): HasMany
    {
        return $this->esCliente() ? $this->conversacionesComoCliente() : $this->conversacionesComoAsesor();
    }

    // ----------------------------------------------------------------
    // Consultas reutilizables
    // ----------------------------------------------------------------

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoUsuario::Activo);
    }

    public function scopeDelRol(Builder $query, RolUsuario $rol): Builder
    {
        return $query->where('rol', $rol);
    }

    // ----------------------------------------------------------------
    // Estado y rol
    // ----------------------------------------------------------------

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::Activo;
    }

    public function tieneRol(RolUsuario ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Administrador;
    }

    public function esAsesor(): bool
    {
        return $this->rol === RolUsuario::Asesor;
    }

    public function esCliente(): bool
    {
        return $this->rol === RolUsuario::Cliente;
    }

    // Impide que un administrador se cambie el rol, se desactive o se elimine a sí mismo
    public function esElMismoQue(?self $otro): bool
    {
        return $otro !== null && $this->getKey() === $otro->getKey();
    }

    // Un usuario con reservas o ventas asociadas no puede eliminarse: se conserva el histórico
    public function tieneHistorial(): bool
    {
        return $this->reservas()->exists() || $this->ventas()->exists();
    }

    public function notificacionesSinLeer(): int
    {
        return $this->notificaciones()->sinLeer()->count();
    }

    // ----------------------------------------------------------------
    // Presentación
    // ----------------------------------------------------------------

    /**
     * URL del avatar. Sin foto propia se genera uno con las iniciales,
     * igual que hacía el panel del prototipo.
     */
    public function getFotoAttribute(): string
    {
        if ($this->foto_url) {
            return asset($this->foto_url);
        }

        return 'https://ui-avatars.com/api/?'.http_build_query([
            'name' => $this->nombre,
            'size' => 128,
            'background' => '0f1e4a',
            'color' => 'fff',
            'rounded' => 'true',
            'bold' => 'true',
        ]);
    }

    public function tieneFotoPropia(): bool
    {
        return filled($this->foto_url);
    }

    public function getInicialesAttribute(): string
    {
        return collect(explode(' ', trim($this->nombre)))
            ->filter()
            ->take(2)
            ->map(fn (string $palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->implode('');
    }
}
