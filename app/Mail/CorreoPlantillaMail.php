<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo cuyo cuerpo ya viene renderizado (RenderizadorCorreo). No usa vistas
 * Blade: el HTML se congela por destinatario con su píxel y sus enlaces
 * firmados, y lo que se manda es exactamente lo que se guarda.
 */
class CorreoPlantillaMail extends Mailable
{
    /**
     * `$html` va sin tipo a propósito: Mailable ya declara `public $html`
     * (lo usa buildView() como cuerpo HTML) y PHP no deja retiparlo al heredar.
     *
     * @param  string  $html
     */
    /**
     * @param  array<int, array{disco: string, ruta: string, nombre: string}>  $adjuntos
     */
    public function __construct(
        public string $asunto,
        public $html,
        public ?string $remitenteNombre = null,
        public ?string $remitenteEmail = null,
        public array $adjuntos = [],
    ) {
    }

    public function envelope(): Envelope
    {
        $from = null;
        if ($this->remitenteEmail) {
            $from = new Address($this->remitenteEmail, $this->remitenteNombre ?: null);
        } elseif ($this->remitenteNombre) {
            // Solo nombre: se respeta la dirección de MAIL_FROM_ADDRESS.
            $from = new Address((string) config('mail.from.address'), $this->remitenteNombre);
        }

        return new Envelope(
            from: $from,
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->html);
    }

    public function attachments(): array
    {
        return array_map(
            fn (array $a) => \Illuminate\Mail\Mailables\Attachment::fromStorageDisk($a['disco'], $a['ruta'])->as($a['nombre']),
            $this->adjuntos
        );
    }
}
