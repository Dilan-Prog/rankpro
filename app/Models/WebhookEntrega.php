<?php

namespace App\Models;

use App\Enums\EstadoEntregaWebhook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEntrega extends Model
{
    protected $table = 'webhook_entregas';

    protected $fillable = [
        'webhook_id', 'evento', 'uuid', 'payload', 'estado', 'intentos',
        'codigo_http', 'respuesta', 'error', 'proximo_intento_en', 'entregado_en',
    ];

    protected $casts = [
        'payload' => 'array',
        'estado' => EstadoEntregaWebhook::class,
        'proximo_intento_en' => 'datetime',
        'entregado_en' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /** Entregas pendientes cuyo próximo intento ya venció: lo que consume el scheduler. */
    public function scopeVencidas($query)
    {
        return $query->where('estado', EstadoEntregaWebhook::Pendiente)
            ->where(function ($q) {
                $q->whereNull('proximo_intento_en')->orWhere('proximo_intento_en', '<=', now());
            });
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'evento' => $this->evento,
            'uuid' => $this->uuid,
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'intentos' => $this->intentos,
            'codigo_http' => $this->codigo_http,
            'respuesta' => $this->respuesta,
            'error' => $this->error,
            'proximo_intento_en' => $this->proximo_intento_en?->format('Y-m-d H:i'),
            'entregado_en' => $this->entregado_en?->format('Y-m-d H:i'),
            'creado_en' => $this->created_at?->format('Y-m-d H:i'),
            'payload' => $this->payload,
        ];
    }
}
