<?php

namespace App\Models;

use App\Enums\SentidoMetrica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definición de una métrica mensual personalizada de un cliente (meta + sentido).
 */
class MetricaCliente extends Model
{
    protected $table = 'metricas_cliente';

    protected $fillable = [
        'cliente_id', 'nombre', 'unidad', 'meta', 'sentido', 'umbral_amarillo_pct', 'activa', 'orden',
    ];

    protected $casts = [
        'meta' => 'decimal:2',
        'sentido' => SentidoMetrica::class,
        'umbral_amarillo_pct' => 'integer',
        'activa' => 'boolean',
        'orden' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function valores(): HasMany
    {
        return $this->hasMany(MetricaClienteValor::class, 'metrica_id');
    }
}
