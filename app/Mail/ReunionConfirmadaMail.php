<?php

namespace App\Mail;

use App\Models\Reunion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo transaccional al prospecto/cliente que agenda: confirma fecha/hora
 * y trae el enlace para cancelar. No usa RenderizadorCorreo (eso es solo
 * para envíos masivos del módulo Correo): es una vista Blade fija y simple.
 */
class ReunionConfirmadaMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Reunion $reunion)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reunión confirmada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reunion-confirmada',
            with: ['reunion' => $this->reunion],
        );
    }
}
