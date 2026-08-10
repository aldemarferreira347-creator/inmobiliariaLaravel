<?php

namespace App\Servicios;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoVenta;
use App\Enumerados\TipoNotificacion;
use App\Models\Inmueble;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de las ventas (HU-14).
 * Una venta en proceso reserva el inmueble; al cancelarla vuelve a liberarse.
 */
class VentaService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly ArchivoPrivadoService $archivos,
    ) {}

    /**
     * @throws ValidationException
     */
    public function registrar(array $datos, User $asesor): Venta
    {
        $venta = DB::transaction(function () use ($datos, $asesor) {
            $inmueble = Inmueble::whereKey($datos['inmueble_id'])->lockForUpdate()->firstOrFail();

            $this->asegurarQueEsVendible($inmueble);

            $venta = Venta::create([...$datos, 'asesor_id' => $asesor->id, 'estado' => EstadoVenta::EnProceso]);

            // HU-14.1: una venta en curso bloquea el inmueble
            $inmueble->update(['estado' => EstadoInmueble::Reservado]);

            return $venta;
        });

        $this->notificaciones->paraUsuario(
            $venta->cliente,
            'Proceso de venta iniciado',
            "Se inició el proceso de venta de «{$venta->inmueble->titulo}». Tu asesor te acompañará en cada paso.",
            TipoNotificacion::Info,
            'inmueble',
            $venta->inmueble_id,
        );

        return $venta;
    }

    public function cerrar(Venta $venta): void
    {
        $this->asegurarQueEstaEnProceso($venta);

        DB::transaction(function () use ($venta) {
            $venta->update(['estado' => EstadoVenta::Cerrada]);
            $venta->inmueble->update(['estado' => EstadoInmueble::Ocupado]);
        });

        $this->notificaciones->paraUsuario(
            $venta->cliente,
            'Venta cerrada',
            "La venta de «{$venta->inmueble->titulo}» quedó cerrada. ¡Felicitaciones!",
            TipoNotificacion::Exito,
            'inmueble',
            $venta->inmueble_id,
        );
    }

    // HU-14.4: cancelar devuelve el inmueble al estado que le corresponda
    public function cancelar(Venta $venta, string $motivo): void
    {
        $this->asegurarQueEstaEnProceso($venta);

        DB::transaction(function () use ($venta) {
            $venta->update(['estado' => EstadoVenta::Cancelada]);

            $inmueble = $venta->inmueble->fresh();
            $inmueble->update(['estado' => $inmueble->estadoCalculado()]);
        });

        $this->notificaciones->paraUsuario(
            $venta->cliente,
            'Proceso de venta cancelado',
            "El proceso de venta de «{$venta->inmueble->titulo}» fue cancelado. Motivo: {$motivo}",
            TipoNotificacion::Aviso,
            'inmueble',
            $venta->inmueble_id,
        );
    }

    public function adjuntarEscritura(Venta $venta, UploadedFile $archivo): void
    {
        $this->archivos->eliminar($venta->escritura_ruta);

        $ruta = $this->archivos->guardar($archivo, 'escrituras', "escritura_{$venta->id}_".now()->timestamp);

        $venta->update(['escritura_ruta' => $ruta]);
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueEsVendible(Inmueble $inmueble): void
    {
        if ($inmueble->estado !== EstadoInmueble::Disponible) {
            throw ValidationException::withMessages([
                'inmueble_id' => 'Este inmueble no está disponible para iniciar una venta.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueEstaEnProceso(Venta $venta): void
    {
        if ($venta->estado !== EstadoVenta::EnProceso) {
            throw ValidationException::withMessages([
                'estado' => 'Esta venta ya está cerrada o cancelada.',
            ]);
        }
    }
}
