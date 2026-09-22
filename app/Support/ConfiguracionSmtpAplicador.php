<?php

namespace App\Support;

use App\Models\ConfiguracionSmtp;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Sobreescribe config('mail.*') en caliente con lo guardado en
 * configuracion_smtp, si existe y está activa. Se llama una vez por petición
 * desde AppServiceProvider::boot() (corre en HTTP y en consola, así que
 * también aplica a `correo:procesar-programados`).
 *
 * A propósito NO se debe cachear con `php artisan config:cache`: eso
 * "congelaría" el valor de este momento y el panel dejaría de tener efecto
 * hasta el próximo `config:clear`. El resto del proyecto ya despliega con
 * `optimize:clear`, nunca con `config:cache` — ver notas de despliegue.
 */
class ConfiguracionSmtpAplicador
{
    public static function aplicar(): void
    {
        try {
            $config = ConfiguracionSmtp::actual();
        } catch (Throwable $e) {
            // Tabla aún no migrada (instalación nueva, o corriendo el propio
            // `migrate` antes de que exista) o BD no disponible: se sigue con
            // los MAIL_* del .env tal cual, sin tumbar el arranque de la app.
            return;
        }

        if (! $config || ! $config->activa || blank($config->host)) {
            return;
        }

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $config->host,
            'mail.mailers.smtp.port' => $config->puerto ?: 587,
            'mail.mailers.smtp.encryption' => $config->cifrado ?: null,
            'mail.mailers.smtp.username' => $config->usuario,
            'mail.mailers.smtp.password' => $config->password,
            'mail.from.address' => $config->remitente_email ?: config('mail.from.address'),
            'mail.from.name' => $config->remitente_nombre ?: config('mail.from.name'),
        ]);
    }
}
