<?php

namespace App\Models;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\EstadoVenta;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use Database\Factories\InmuebleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Inmueble (tabla `inmueble`).
 *
 * Concentra las reglas de negocio del catálogo: filtros públicos, precios a
 * mostrar según modalidad y, sobre todo, el estado, que es un valor derivado
 * de `reserva` y `contrato` (HU-09).
 */
class Inmueble extends Model
{
    /** @use HasFactory<InmuebleFactory> */
    use HasFactory;

    public const DESCRIPCION_MINIMA = 50;

    protected $table = 'inmueble';

    public const CREATED_AT = 'fecha_publicacion';

    public const UPDATED_AT = null;

    protected $fillable = [
        'codigo',
        'titulo',
        'descripcion',
        'tipo',
        'modalidad',
        'estado',
        'precio_venta',
        'precio_arrendamiento',
        'ciudad',
        'barrio',
        'direccion',
        'habitaciones',
        'banos',
        'area',
        'parqueadero',
        'imagen',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoInmueble::class,
            'modalidad' => ModalidadInmueble::class,
            'estado' => EstadoInmueble::class,
            'precio_venta' => 'decimal:2',
            'precio_arrendamiento' => 'decimal:2',
            'area' => 'decimal:2',
            'habitaciones' => 'integer',
            'banos' => 'integer',
            'parqueadero' => 'boolean',
            'fecha_publicacion' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relaciones
    // ----------------------------------------------------------------

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenInmueble::class, 'inmueble_id')->orderBy('orden');
    }

    public function imagenPrincipal(): HasOne
    {
        return $this->hasOne(ImagenInmueble::class, 'inmueble_id')->where('es_principal', true);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'inmueble_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'inmueble_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'inmueble_id');
    }

    public function usuariosQueLoMarcaron(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorito', 'inmueble_id', 'usuario_id');
    }

    // ----------------------------------------------------------------
    // Consultas del catálogo
    // ----------------------------------------------------------------

    /**
     * Aplica los filtros del catálogo público (HU-02).
     *
     * Claves admitidas: ubicacion, modalidad, tipo, precio_min, precio_max,
     * habitaciones, codigo. Las vacías se ignoran, de modo que combinarlas
     * es acumulativo y omitirlas todas devuelve el listado completo.
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        $modalidad = $this->normalizarModalidad($filtros['modalidad'] ?? null);

        return $query
            ->when(
                filled($filtros['ubicacion'] ?? null),
                fn (Builder $q) => $q->where(function (Builder $q) use ($filtros) {
                    $q->where('ciudad', 'like', '%'.$filtros['ubicacion'].'%')
                        ->orWhere('barrio', 'like', '%'.$filtros['ubicacion'].'%');
                })
            )
            // Un inmueble en modalidad «ambos» aparece tanto en venta como en arriendo
            ->when(
                $modalidad !== null && $modalidad !== ModalidadInmueble::Ambos,
                fn (Builder $q) => $q->whereIn('modalidad', [$modalidad, ModalidadInmueble::Ambos])
            )
            ->when(filled($filtros['tipo'] ?? null), fn (Builder $q) => $q->where('tipo', $filtros['tipo']))
            ->when(filled($filtros['codigo'] ?? null), fn (Builder $q) => $q->where('codigo', 'like', '%'.$filtros['codigo'].'%'))
            ->when(filled($filtros['habitaciones'] ?? null), fn (Builder $q) => $q->where('habitaciones', '>=', (int) $filtros['habitaciones']))
            ->when(filled($filtros['precio_min'] ?? null), fn (Builder $q) => $this->acotarPrecio($q, $modalidad, '>=', (float) $filtros['precio_min']))
            ->when(filled($filtros['precio_max'] ?? null), fn (Builder $q) => $this->acotarPrecio($q, $modalidad, '<=', (float) $filtros['precio_max']));
    }

    /**
     * Acota por precio usando la columna que corresponde a la modalidad pedida.
     * Sin modalidad, cada inmueble se compara contra el precio que le aplica.
     */
    private function acotarPrecio(Builder $query, ?ModalidadInmueble $modalidad, string $operador, float $valor): Builder
    {
        if ($modalidad === ModalidadInmueble::Venta) {
            return $query->where('precio_venta', $operador, $valor);
        }

        if ($modalidad === ModalidadInmueble::Arriendo) {
            return $query->where('precio_arrendamiento', $operador, $valor);
        }

        return $query->where(fn (Builder $q) => $q
            ->where('precio_venta', $operador, $valor)
            ->orWhere('precio_arrendamiento', $operador, $valor));
    }

    private function normalizarModalidad(mixed $valor): ?ModalidadInmueble
    {
        return $valor instanceof ModalidadInmueble
            ? $valor
            : ModalidadInmueble::tryFrom((string) $valor);
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('fecha_publicacion');
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estado', EstadoInmueble::Disponible);
    }

    // ----------------------------------------------------------------
    // Estado derivado (HU-09)
    // ----------------------------------------------------------------

    /**
     * Calcula el estado real del inmueble consultando sus tablas relacionadas.
     *
     * Prioridad decreciente:
     *   1. Ocupado    → contrato de arriendo vigente o venta cerrada
     *   2. Reservado  → reserva viva (incluida la confirmada, cuyo contrato aún
     *                   no se emite) o venta en curso
     *   3. Disponible → ninguna de las anteriores
     */
    public function estadoCalculado(): EstadoInmueble
    {
        if ($this->tieneOcupacionVigente()) {
            return EstadoInmueble::Ocupado;
        }

        if ($this->tieneReservaEnProceso()) {
            return EstadoInmueble::Reservado;
        }

        return EstadoInmueble::Disponible;
    }

    // Ocupan el inmueble un contrato de arriendo vigente o una venta cerrada
    public function tieneOcupacionVigente(): bool
    {
        return $this->reservas()
            ->whereHas('contratos', fn (Builder $c) => $c->where('estado', EstadoContrato::Vigente))
            ->exists()
            || $this->ventas()->where('estado', EstadoVenta::Cerrada)->exists();
    }

    /**
     * Una reserva viva o una venta en curso reservan el inmueble (HU-09.1 / HU-14.1).
     *
     * Una reserva confirmada lo retiene mientras su contrato esté por emitirse;
     * si el contrato ya se emitió y luego venció o se rescindió, esa reserva
     * deja de retenerlo y el inmueble vuelve al catálogo (HU-17.3 / HU-17.4).
     */
    public function tieneReservaEnProceso(): bool
    {
        $reservada = $this->reservas()
            ->where(fn (Builder $q) => $q
                ->whereIn('estado', EstadoReserva::bloqueanInmueble())
                ->orWhere(fn (Builder $c) => $c
                    ->where('estado', EstadoReserva::Confirmada)
                    ->whereDoesntHave('contratos')))
            ->exists();

        return $reservada || $this->ventas()->where('estado', EstadoVenta::EnProceso)->exists();
    }

    /**
     * Solicitudes nuevas: solo miran las reservas que aún no se resolvieron.
     * Una confirmada ya bloquea el inmueble por su propio estado.
     */
    public function tieneSolicitudPendiente(): bool
    {
        return $this->reservas()->whereIn('estado', EstadoReserva::bloqueanInmueble())->exists();
    }

    /**
     * Indica si el administrador puede fijar manualmente un estado.
     *
     * «Disponible» es una transición libre —permite liberar el inmueble—, pero
     * «Reservado» y «Ocupado» exigen que los datos relacionales los respalden.
     */
    public function admiteEstado(EstadoInmueble $estado): bool
    {
        return match ($estado) {
            EstadoInmueble::Disponible => true,
            EstadoInmueble::Reservado => $this->tieneReservaEnProceso(),
            EstadoInmueble::Ocupado => $this->tieneOcupacionVigente(),
        };
    }

    // Motivo por el que un estado fue rechazado, listo para mostrar al usuario
    public function motivoEstadoNoAdmitido(EstadoInmueble $estado): string
    {
        return match ($estado) {
            EstadoInmueble::Reservado => 'No se puede marcar como Reservado: el inmueble no tiene una reserva activa en proceso.',
            EstadoInmueble::Ocupado => 'No se puede marcar como Ocupado: el inmueble no tiene un contrato activo ni una reserva confirmada.',
            EstadoInmueble::Disponible => '',
        };
    }

    // Un inmueble con reservas vivas no se elimina: se conserva el histórico (HU-04.5)
    public function tieneReservasActivas(): bool
    {
        return $this->reservas()->whereIn('estado', EstadoReserva::activas())->exists();
    }

    // ----------------------------------------------------------------
    // Código
    // ----------------------------------------------------------------

    // Genera un código único con el prefijo INM-, reintentando si ya existe
    public static function generarCodigo(): string
    {
        do {
            $codigo = 'INM-'.Str::upper(Str::random(6));
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }

    // ----------------------------------------------------------------
    // Presentación
    // ----------------------------------------------------------------

    /**
     * Precios a mostrar según la modalidad, ya formateados.
     * Si el inmueble no tiene ninguno, devuelve «Consultar» para no dejar
     * la tarjeta en blanco.
     *
     * @return array<int, array{label: string, valor: string, tipo: string}>
     */
    public function getPreciosAttribute(): array
    {
        $precios = [];

        if ($this->modalidad->exigePrecioVenta() && $this->precio_venta !== null) {
            $precios[] = [
                'label' => 'Precio de venta',
                'valor' => $this->formatearMoneda($this->precio_venta),
                'tipo' => 'venta',
            ];
        }

        if ($this->modalidad->exigePrecioArriendo() && $this->precio_arrendamiento !== null) {
            $precios[] = [
                'label' => 'Precio de arriendo',
                'valor' => $this->formatearMoneda($this->precio_arrendamiento).' / mes',
                'tipo' => 'arriendo',
            ];
        }

        return $precios ?: [[
            'label' => 'Precio',
            'valor' => 'Consultar',
            'tipo' => 'consultar',
        ]];
    }

    private function formatearMoneda(string|float $valor): string
    {
        return '$'.number_format((float) $valor, 0, ',', '.');
    }

    public function getImagenUrlAttribute(): string
    {
        return $this->urlDeImagen($this->imagen);
    }

    /**
     * Resuelve la URL pública de una imagen del inmueble.
     * Las URL absolutas se respetan tal cual; las rutas relativas se sirven
     * desde public/. Sin valor se genera un placeholder legible con el título,
     * para que la tarjeta nunca quede con un hueco.
     */
    public function urlDeImagen(?string $valor): string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 'https://placehold.co/600x400/1e3c72/white?text='.rawurlencode($this->titulo ?: 'Inmueble');
        }

        return Str::startsWith($valor, ['http://', 'https://']) ? $valor : asset($valor);
    }

    public function getUbicacionAttribute(): string
    {
        return "{$this->barrio}, {$this->ciudad}";
    }

    public function esFavoritoDe(?User $usuario): bool
    {
        return $usuario !== null
            && $this->usuariosQueLoMarcaron()->whereKey($usuario->getKey())->exists();
    }
}
