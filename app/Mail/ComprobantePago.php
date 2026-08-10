<?php

namespace App\Mail;

use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-23.1: comprobante que recibe el cliente cuando su pago queda confirmado
class ComprobantePago extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Pago $pago) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Comprobante de pago — Reserva {$this->pago->reserva->codigo_reserva}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.comprobante-pago');
    }
}
