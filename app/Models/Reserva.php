<?php

namespace App\Models;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use Database\Factories\ReservaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reserva de un inmueble (HU-07 / HU-23).
 *
 * El acceso a datos vive aquí; las transiciones de estado las aplica
 * App\Servicios\ReservaService, que es quien conoce las reglas de negocio.
 */
class Reserva extends Model
{
    /** @use HasFactory<ReservaFactory> */
    use HasFactory, SoftDeletes;

    /** Horas que tiene el cliente para pagar antes de que la reserva expire */
    public const HORAS_PARA_PAGAR = 24;

    protected $table = 'reserva';

    protected $fillable = [
        'codigo_reserva',
        'inmueble_id',
        'usuario_id',
        'asesor_id',
        'monto_reserva',
        'estado',
        'expira_en',
        'notas_cliente',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoReserva::class,
            'monto_reserva' => 'decimal:2',
            'expira_en' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relaciones
    // ----------------------------------------------------------------

    public function inmueble(): BelongsTo
    {
        return $this->belongsTo(Inmueble::class, 'inmueble_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'reserva_id')->latest('id');
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class, 'reserva_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialReserva::class, 'reserva_id')->oldest('id');
    }

    // Compatibilidad con Inmueble::tieneOcupacionVigente(), que consulta contratos vigentes
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'reserva_id');
    }

    // ----------------------------------------------------------------
    // Consultas
    // ----------------------------------------------------------------

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->latest('created_at');
    }

    public function scopeEnEstado(Builder $query, EstadoReserva ...$estados): Builder
    {
        return $query->whereIn('estado', $estados);
    }

    // Reservas cuyo plazo de pago venció y siguen esperando (HU-09.2)
    public function scopeVencidas(Builder $query): Builder
    {
        return $query
            ->where('estado', EstadoReserva::PendientePago)
            ->where('expira_en', '<', now());
    }

    // ----------------------------------------------------------------
    // Estado
    // ----------------------------------------------------------------

    public function estaVencida(): bool
    {
        return $this->expira_en->isPast();
    }

    public function pagoEnRevision(): ?Pago
    {
        return $this->pagos()
            ->whereIn('estado', [EstadoPago::Pendiente, EstadoPago::Procesando])
            ->first();
    }

    public function ultimoPagoRechazado(): ?Pago
    {
        return $this->pagos()->where('estado', EstadoPago::Rechazado)->first();
    }

    // El cliente solo registra un pago si la reserva lo admite y no hay otro en revisión
    public function admiteNuevoPago(): bool
    {
        return $this->estado->admitePago() && ! $this->estaVencida() && $this->pagoEnRevision() === null;
    }

    /**
     * Momento en que la reserva se confirmó por primera vez.
     * Es el punto de partida del plazo de 7 días para emitir el contrato (RN-18).
     */
    public function confirmadaEn(): ?Carbon
    {
        return $this->historial()
            ->where('estado_nuevo', EstadoReserva::Confirmada->value)
            ->value('creado_en');
    }

    public function tieneContratoVigente(): bool
    {
        return $this->contrato()->where('estado', EstadoContrato::Vigente)->exists();
    }

    // ----------------------------------------------------------------
    // Presentación
    // ----------------------------------------------------------------

    public static function generarCodigo(): string
    {
        do {
            $codigo = 'RES-'.Str::upper(Str::random(8));
        } while (static::withTrashed()->where('codigo_reserva', $codigo)->exists());

        return $codigo;
    }

    public function getMontoFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->monto_reserva, 0, ',', '.');
    }
}
