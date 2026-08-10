<?php

namespace App\Servicios;

use App\Enumerados\EstadoContrato;
use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoReserva;
use App\Enumerados\TipoNotificacion;
use App\Models\Contrato;
use App\Models\Reserva;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de los contratos de arriendo (HU-17).
 *
 * RN-18: el contrato debe emitirse dentro de los 7 días naturales siguientes a
 * la confirmación de la reserva. Pasado ese plazo hay que rehacer la operación.
 */
class ContratoService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly ArchivoPrivadoService $archivos,
    ) {}

    /**
     * @throws ValidationException
     */
    public function crearDesdeReserva(Reserva $reserva, array $datos): Contrato
    {
        $this->asegurarQueAdmiteContrato($reserva);

        $contrato = DB::transaction(function () use ($reserva, $datos) {
            $contrato = Contrato::create([
                'reserva_id' => $reserva->id,
                'numero_contrato' => Contrato::generarNumero($reserva),
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'valor_mensual' => $datos['valor_mensual'],
            ]);

            // Un contrato vigente ocupa el inmueble (HU-17.1)
            $reserva->inmueble->update(['estado' => EstadoInmueble::Ocupado]);

            return $contrato;
        });

        $this->notificaciones->paraUsuario(
            $reserva->cliente,
            'Contrato emitido',
            "Tu contrato {$contrato->numero_contrato} ya está disponible en «Mis arriendos».",
            TipoNotificacion::Exito,
            'contrato',
            $contrato->id,
            conCorreo: true,
        );

        return $contrato;
    }

    // HU-17.2: adjunta el PDF firmado, reemplazando el anterior si lo hubiera
    public function adjuntarDocumento(Contrato $contrato, UploadedFile $archivo): void
    {
        $this->archivos->eliminar($contrato->archivo_ruta);

        $ruta = $this->archivos->guardar($archivo, 'contratos', "contrato_{$contrato->id}_".now()->timestamp);

        $contrato->update(['archivo_ruta' => $ruta]);
    }

    // HU-17.4: rescindir libera el inmueble y avisa al cliente
    public function rescindir(Contrato $contrato, string $motivo): void
    {
        $this->asegurarQueEstaVigente($contrato);

        DB::transaction(function () use ($contrato) {
            $contrato->update(['estado' => EstadoContrato::Rescindido]);
            $this->liberarInmueble($contrato);
        });

        $this->notificaciones->paraUsuario(
            $contrato->reserva->cliente,
            'Contrato rescindido',
            "Tu contrato {$contrato->numero_contrato} fue rescindido. Motivo: {$motivo}",
            TipoNotificacion::Aviso,
            'contrato',
            $contrato->id,
            conCorreo: true,
        );
    }

    /**
     * HU-17.3: marca como vencidos los contratos cuya fecha de fin ya pasó.
     * A diferencia del prototipo, aquí el vencimiento también libera el
     * inmueble, que de otro modo quedaba Ocupado indefinidamente.
     */
    public function vencer(Contrato $contrato): void
    {
        DB::transaction(function () use ($contrato) {
            $contrato->update(['estado' => EstadoContrato::Vencido]);
            $this->liberarInmueble($contrato);
        });

        $this->notificaciones->paraStaff(
            'Contrato vencido',
            "El contrato {$contrato->numero_contrato} llegó a su fecha de fin y el inmueble volvió a estar disponible.",
            TipoNotificacion::Aviso,
            'contrato',
            $contrato->id,
        );
    }

    // ----------------------------------------------------------------
    // Apoyo
    // ----------------------------------------------------------------

    private function liberarInmueble(Contrato $contrato): void
    {
        $inmueble = $contrato->reserva->inmueble->fresh();

        $inmueble->update(['estado' => $inmueble->estadoCalculado()]);
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueAdmiteContrato(Reserva $reserva): void
    {
        if ($reserva->estado !== EstadoReserva::Confirmada) {
            throw ValidationException::withMessages([
                'reserva_id' => 'Solo se puede emitir un contrato desde una reserva confirmada.',
            ]);
        }

        if ($reserva->contrato()->exists()) {
            throw ValidationException::withMessages([
                'reserva_id' => 'Esta reserva ya tiene un contrato emitido.',
            ]);
        }

        $confirmada = $reserva->confirmadaEn();
        $limite = $confirmada?->copy()->addDays(Contrato::DIAS_PARA_EMITIR);

        if ($limite !== null && now()->greaterThan($limite)) {
            throw ValidationException::withMessages([
                'reserva_id' => 'Venció el plazo de '.Contrato::DIAS_PARA_EMITIR
                    .' días naturales para emitir el contrato de esta reserva.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueEstaVigente(Contrato $contrato): void
    {
        if ($contrato->estado !== EstadoContrato::Vigente) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede rescindir un contrato vigente.',
            ]);
        }
    }
}
