<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo genérico de aviso.
 *
 * Cubre los mensajes de una sola idea —bienvenida, cambios de estado de una
 * reserva, contrato emitido, notificación del administrador— para no repetir
 * una clase y una plantilla casi idénticas por cada caso.
 */
class Aviso extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $parrafos
     * @param  array{texto: string, url: string}|null  $accion
     */
    public function __construct(
        public readonly string $titulo,
        public readonly array $parrafos,
        public readonly ?array $accion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso');
    }
}
