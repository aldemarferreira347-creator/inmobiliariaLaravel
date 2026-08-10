<?php

namespace App\Servicios\Reportes;

use App\Enumerados\PeriodoReporte;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Criterios con los que se acota un reporte.
 *
 * Un rango explícito de fechas manda sobre el periodo de las pestañas: si el
 * administrador escribe unas fechas, son esas las que se usan.
 */
final class FiltroReporte
{
    public function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly ?Carbon $desde,
        public readonly ?Carbon $hasta,
        public readonly ?string $estado,
    ) {}

    public static function desdePeticion(Request $peticion): self
    {
        $datos = $peticion->validate([
            'periodo' => ['nullable', 'string'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', 'string', 'max:50'],
        ]);

        return new self(
            periodo: PeriodoReporte::tryFrom($datos['periodo'] ?? '') ?? PeriodoReporte::porDefecto(),
            desde: isset($datos['desde']) ? Carbon::parse($datos['desde'])->startOfDay() : null,
            hasta: isset($datos['hasta']) ? Carbon::parse($datos['hasta'])->endOfDay() : null,
            estado: $datos['estado'] ?? null,
        );
    }

    public function inicio(): Carbon
    {
        return $this->desde ?? Carbon::instance($this->periodo->inicio()->toDateTime());
    }

    public function fin(): Carbon
    {
        return $this->hasta ?? Carbon::now()->endOfDay();
    }

    public function usaRangoPropio(): bool
    {
        return $this->desde !== null || $this->hasta !== null;
    }

    /** Descripción legible del rango, para la cabecera de los archivos exportados */
    public function descripcion(): string
    {
        if ($this->usaRangoPropio()) {
            return $this->inicio()->format('d/m/Y').' — '.$this->fin()->format('d/m/Y');
        }

        return $this->periodo->etiqueta();
    }

    /** @return array<string, string> parámetros para reconstruir la URL */
    public function comoParametros(): array
    {
        return array_filter([
            'periodo' => $this->periodo->value,
            'desde' => $this->desde?->format('Y-m-d'),
            'hasta' => $this->hasta?->format('Y-m-d'),
            'estado' => $this->estado,
        ]);
    }
}
