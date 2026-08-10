<?php

namespace App\Servicios;

use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Models\Conversacion;
use App\Models\Inmueble;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mensajería entre clientes y equipo interno (HU-13).
 *
 * El hilo es una entidad propia (cliente + asesor + inmueble), no una consulta
 * que agrupa por pares de emisor y receptor como hacía el prototipo.
 */
class MensajeService
{
    private const DIRECTORIO_ADJUNTOS = 'chat';

    public function __construct(private readonly NotificacionService $notificaciones) {}

    /**
     * Abre —o recupera— el hilo entre un cliente y el equipo para un inmueble.
     *
     * @throws ValidationException si no hay ningún asesor disponible
     */
    public function abrirConversacion(User $cliente, Inmueble $inmueble): Conversacion
    {
        $existente = Conversacion::query()
            ->where('cliente_id', $cliente->id)
            ->where('inmueble_id', $inmueble->id)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return Conversacion::create([
            'cliente_id' => $cliente->id,
            'asesor_id' => $this->asesorPara($cliente)->id,
            'inmueble_id' => $inmueble->id,
        ]);
    }

    // Publica un mensaje y avisa al interlocutor
    public function enviar(Conversacion $conversacion, User $emisor, ?string $texto, ?UploadedFile $adjunto): Mensaje
    {
        if (blank($texto) && $adjunto === null) {
            throw ValidationException::withMessages([
                'contenido' => 'Escribe un mensaje o adjunta una imagen.',
            ]);
        }

        $mensaje = DB::transaction(function () use ($conversacion, $emisor, $texto, $adjunto) {
            $mensaje = $conversacion->mensajes()->create([
                'emisor_id' => $emisor->id,
                'contenido' => $texto,
                'adjunto_url' => $adjunto ? $this->guardarAdjunto($adjunto) : null,
            ]);

            $conversacion->update(['ultimo_mensaje_en' => $mensaje->creado_en]);

            return $mensaje;
        });

        $destinatario = $conversacion->interlocutorDe($emisor);

        $this->notificaciones->paraUsuario(
            $destinatario,
            'Nuevo mensaje',
            "{$emisor->nombre} te escribió sobre «{$conversacion->inmueble?->titulo}».",
            TipoNotificacion::Info,
            'conversacion',
            $conversacion->id,
            conCorreo: true,
        );

        return $mensaje;
    }

    // Marca como leídos los mensajes que el usuario recibió en este hilo
    public function marcarLeidos(Conversacion $conversacion, User $lector): void
    {
        $conversacion->mensajes()
            ->whereNull('leido_en')
            ->where('emisor_id', '!=', $lector->id)
            ->update(['leido_en' => now()]);
    }

    /**
     * Elige el asesor que atenderá al cliente.
     *
     * Se mantiene el que ya lo venía atendiendo, para no partir la relación; si
     * no hay ninguno, se reparte entre los asesores activos por carga de trabajo
     * en vez de al azar como hacía el prototipo.
     *
     * @throws ValidationException
     */
    private function asesorPara(User $cliente): User
    {
        $previo = Conversacion::where('cliente_id', $cliente->id)
            ->latest('id')
            ->first()?->asesor;

        if ($previo?->estaActivo()) {
            return $previo;
        }

        $asesor = User::query()
            ->activos()
            ->whereIn('rol', [RolUsuario::Asesor, RolUsuario::Administrador])
            ->withCount('conversacionesComoAsesor')
            ->orderBy('conversaciones_como_asesor_count')
            ->first();

        if ($asesor === null) {
            throw ValidationException::withMessages([
                'asesor' => 'No hay asesores disponibles en este momento. Inténtalo más tarde.',
            ]);
        }

        return $asesor;
    }

    private function guardarAdjunto(UploadedFile $archivo): string
    {
        return 'storage/'.$archivo->store(self::DIRECTORIO_ADJUNTOS, 'public');
    }
}
