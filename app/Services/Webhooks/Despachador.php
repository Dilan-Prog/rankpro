<?php

namespace App\Services\Webhooks;

use App\Models\Webhook;
use App\Models\WebhookEntrega;
use App\Support\Webhooks\Eventos;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Entrega webhooks salientes sin colas (el hosting no tiene worker): emitir()
 * solo inserta filas 'pendiente' y las apunta en memoria; el POST real ocurre
 * en app()->terminating() (después de que el usuario ya recibió la respuesta
 * del panel) o, en consola/tests, cuando el llamador invoca vaciar()
 * explícitamente. Lo que falla se reintenta con backoff vía
 * webhooks:reintentar (scheduler, cada minuto).
 */
class Despachador
{
    /** @var array<int, int> ids de WebhookEntrega pendientes de esta petición */
    private array $pendientes = [];

    private bool $registrado = false;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function emitir(string $evento, array $datos): void
    {
        if (! Eventos::existe($evento)) {
            throw new \InvalidArgumentException("Evento de webhook desconocido: {$evento}");
        }

        $webhooks = Webhook::query()->where('activo', true)->get()->filter->escuchaA($evento);
        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'evento' => $evento,
            'ocurrido_en' => now()->toIso8601String(),
            'datos' => $datos,
        ];

        foreach ($webhooks as $webhook) {
            $entrega = $webhook->entregas()->create([
                'evento' => $evento,
                'uuid' => $payload['id'],
                'payload' => $payload,
                'estado' => 'pendiente',
            ]);
            $this->pendientes[] = $entrega->id;
        }

        if (! $this->registrado && app()->runningInConsole() === false) {
            $this->registrado = true;
            app()->terminating(fn () => $this->vaciar());
        }
    }

    /** Manda todas las entregas apuntadas en esta petición (o las que se le pasen). */
    public function vaciar(): void
    {
        if (! $this->pendientes) {
            return;
        }

        $ids = $this->pendientes;
        $this->pendientes = [];

        WebhookEntrega::whereIn('id', $ids)->where('estado', 'pendiente')->get()
            ->each(fn (WebhookEntrega $e) => $this->entregar($e));
    }

    public function entregar(WebhookEntrega $entrega): WebhookEntrega
    {
        $webhook = $entrega->webhook;
        $timestamp = (string) now()->timestamp;
        $cuerpo = json_encode($entrega->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $firma = hash_hmac('sha256', "{$timestamp}.{$cuerpo}", $webhook->secreto);

        try {
            $respuesta = Http::withBody($cuerpo, 'application/json')
                ->withHeaders([
                    'X-RankPro-Event' => $entrega->evento,
                    'X-RankPro-Delivery' => $entrega->uuid,
                    'X-RankPro-Timestamp' => $timestamp,
                    'X-RankPro-Signature' => "sha256={$firma}",
                ])
                ->timeout((int) config('api.webhooks.timeout', 4))
                ->connectTimeout(2)
                ->post($webhook->url);

            $entrega->intentos++;

            if ($respuesta->successful()) {
                $entrega->update([
                    'estado' => 'entregado',
                    'codigo_http' => $respuesta->status(),
                    'respuesta' => substr($respuesta->body(), 0, 2000),
                    'entregado_en' => now(),
                    'intentos' => $entrega->intentos,
                ]);
                $webhook->update(['ultimo_disparo_en' => now()]);

                return $entrega->fresh();
            }

            $this->marcarFallo($entrega, $respuesta->status(), substr($respuesta->body(), 0, 2000), null);
        } catch (\Throwable $e) {
            Log::warning('Webhook no entregado', ['webhook_id' => $webhook->id, 'evento' => $entrega->evento, 'error' => $e->getMessage()]);
            $entrega->intentos++;
            $this->marcarFallo($entrega, null, null, substr($e->getMessage(), 0, 500));
        }

        return $entrega->fresh();
    }

    /** Crea y entrega de inmediato un evento 'ping' (la UI necesita el resultado ya). */
    public function probar(Webhook $webhook): WebhookEntrega
    {
        $entrega = $webhook->entregas()->create([
            'evento' => 'ping',
            'uuid' => (string) Str::uuid(),
            'payload' => ['id' => (string) Str::uuid(), 'evento' => 'ping', 'ocurrido_en' => now()->toIso8601String(), 'datos' => ['mensaje' => 'Prueba desde el panel de RankPro']],
            'estado' => 'pendiente',
        ]);

        return $this->entregar($entrega);
    }

    private function marcarFallo(WebhookEntrega $entrega, ?int $codigo, ?string $respuesta, ?string $error): void
    {
        $maxIntentos = (int) config('api.webhooks.max_intentos', 6);
        $backoff = config('api.webhooks.backoff', [1, 5, 15, 60, 240, 720]);
        $agotado = $entrega->intentos >= $maxIntentos;

        $entrega->update([
            'estado' => $agotado ? 'fallido' : 'pendiente',
            'codigo_http' => $codigo,
            'respuesta' => $respuesta,
            'error' => $error,
            'intentos' => $entrega->intentos,
            'proximo_intento_en' => $agotado ? null : now()->addMinutes($backoff[$entrega->intentos - 1] ?? end($backoff)),
        ]);
    }
}
