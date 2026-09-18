<?php

namespace App\Services\Correo;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Mail\CorreoPlantillaMail;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Support\Correo\RenderizadorCorreo;
use App\Support\Correo\Variables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Manda los envíos del módulo Correo, de forma síncrona y destinatario a
 * destinatario.
 *
 * Síncrono a propósito: el hosting no tiene worker de colas y los envíos son
 * de decenas de destinatarios, no de miles. Cada destinatario se procesa en su
 * propio try/catch para que un rebote o un SMTP caído a mitad no deje al resto
 * sin correo ni al envío colgado en `enviando`.
 */
class EnviadorCorreo
{
    public function enviar(CorreoEnvio $envio): CorreoEnvio
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        if (! in_array($envio->estado, [EstadoEnvioCorreo::Borrador, EstadoEnvioCorreo::Programado, EstadoEnvioCorreo::Enviando], true)) {
            throw new RuntimeException("El envío #{$envio->id} está en estado '{$envio->estado->value}' y no se puede mandar.");
        }

        $envio->loadMissing('plantilla');
        $plantilla = $envio->plantilla;
        if (! $plantilla instanceof CorreoPlantilla) {
            throw new RuntimeException("El envío #{$envio->id} no tiene plantilla.");
        }

        $variablesEnvio = self::limpiar($envio->variables);

        // El HTML congelado es el "genérico" del envío: sin píxel ni token, con
        // las variables del envío y huecos donde falten las de persona. Es lo
        // que se enseña en el detalle aunque la plantilla cambie después.
        $envio->forceFill([
            'estado' => EstadoEnvioCorreo::Enviando,
            'html_congelado' => RenderizadorCorreo::renderPlantilla($plantilla, $variablesEnvio),
        ])->save();

        $salieron = 0;

        $envio->destinatarios()
            ->where('estado', EstadoDestinatarioCorreo::Pendiente)
            ->with('cliente')
            ->get()
            ->each(function (CorreoDestinatario $destinatario) use ($envio, $plantilla, $variablesEnvio, &$salieron) {
                if ($this->enviarA($destinatario, $envio, $plantilla, $variablesEnvio)) {
                    $salieron++;
                }
            });

        $envio->forceFill([
            'estado' => $salieron > 0 ? EstadoEnvioCorreo::Enviado : EstadoEnvioCorreo::Fallido,
            'enviado_en' => $salieron > 0 ? now() : $envio->enviado_en,
        ])->save();

        return $envio->fresh(['plantilla', 'destinatarios']);
    }

    /**
     * Correo de prueba al usuario que redacta: sin envío, sin píxel ni enlaces
     * firmados; lo que falte se rellena con los ejemplos del catálogo.
     *
     * @param  array<string, mixed>  $variables
     */
    public function prueba(CorreoPlantilla $plantilla, string $asunto, array $variables, string $email, ?string $remitenteNombre = null, ?string $remitenteEmail = null): void
    {
        $variables = array_replace(RenderizadorCorreo::variablesEjemplo(), self::limpiar($variables));

        $html = RenderizadorCorreo::renderPlantilla($plantilla, $variables);
        $asunto = '[Prueba] '.Variables::sustituir($asunto, $variables);

        Mail::to($email)->send(new CorreoPlantillaMail($asunto, $html, $remitenteNombre ?: null, $remitenteEmail ?: null));
    }

    /**
     * Un destinatario: true si salió. Nunca lanza; el error queda en la fila.
     *
     * @param  array<string, string>  $variablesEnvio
     */
    private function enviarA(CorreoDestinatario $destinatario, CorreoEnvio $envio, CorreoPlantilla $plantilla, array $variablesEnvio): bool
    {
        $email = trim((string) $destinatario->email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $destinatario->forceFill([
                'estado' => EstadoDestinatarioCorreo::Fallido,
                'error' => 'Dirección de correo no válida.',
            ])->save();

            return false;
        }

        // Prioridad: envío < cliente del CRM < variables propias del destinatario.
        $variables = array_replace(
            $variablesEnvio,
            $destinatario->cliente ? Variables::desdeCliente($destinatario->cliente) : [],
            self::limpiar($destinatario->variables)
        );

        try {
            $html = RenderizadorCorreo::renderPlantilla($plantilla, $variables, [
                'pixel_url' => RenderizadorCorreo::pixelUrl($destinatario->token),
                'token' => $destinatario->token,
            ]);
            $asunto = Variables::sustituir($envio->asunto, $variables);

            Mail::to($email, $destinatario->nombre ?: null)
                ->send(new CorreoPlantillaMail($asunto, $html, $envio->remitente_nombre, $envio->remitente_email));

            $destinatario->forceFill([
                'estado' => EstadoDestinatarioCorreo::Enviado,
                'enviado_en' => now(),
                'error' => null,
            ])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('Correo: fallo al enviar a destinatario', [
                'envio_id' => $envio->id,
                'destinatario_id' => $destinatario->id,
                'error' => $e->getMessage(),
            ]);

            $destinatario->forceFill([
                'estado' => EstadoDestinatarioCorreo::Fallido,
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    /**
     * Variables como mapa clave => string, descartando nulos (un null pisaría
     * al valor del nivel anterior en array_replace).
     *
     * @return array<string, string>
     */
    private static function limpiar(mixed $variables): array
    {
        $salida = [];
        foreach ((array) $variables as $clave => $valor) {
            if ($valor === null || is_array($valor)) {
                continue;
            }
            $salida[(string) $clave] = (string) $valor;
        }

        return $salida;
    }
}
