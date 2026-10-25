<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Propiedad de Search Console asignada a un cliente (https://dominio.com/ o sc-domain:dominio.com). */
class GscPropiedad extends Model
{
    protected $table = 'gsc_propiedades';

    protected $fillable = ['cliente_id', 'site_url', 'activa', 'ultima_sync_en', 'ultimo_ok', 'ultimo_resultado'];

    protected $casts = [
        'activa' => 'boolean',
        'ultima_sync_en' => 'datetime',
        'ultimo_ok' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function metricas(): HasMany
    {
        return $this->hasMany(GscMetricaMensual::class, 'propiedad_id')->orderByDesc('periodo');
    }

    /** Forma estable para la pantalla y la API; no incluye nada de la llave. */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->nombre,
            'site_url' => $this->site_url,
            'activa' => $this->activa,
            'ultima_sync_en' => $this->ultima_sync_en?->toIso8601String(),
            'ultimo_ok' => $this->ultimo_ok,
            'ultimo_resultado' => $this->ultimo_resultado,
        ];
    }
}
