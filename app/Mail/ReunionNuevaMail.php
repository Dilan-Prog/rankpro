<?php

namespace App\Mail;

use App\Models\Reunion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso a la agencia de que se agendó una reunión nueva. No usa
 * RenderizadorCorreo: es una vista Blade fija y simple.
 */
class ReunionNuevaMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Reunion $reunion)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva reunión agendada: '.$this->reunion->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reunion-nueva',
            with: ['reunion' => $this->reunion],
        );
    }
}
