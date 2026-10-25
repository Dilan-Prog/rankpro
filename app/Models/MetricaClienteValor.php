<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor capturado de una métrica en un mes; `periodo` es siempre el día 1.
 */
class MetricaClienteValor extends Model
{
    protected $table = 'metrica_cliente_valores';

    protected $fillable = ['metrica_id', 'periodo', 'valor', 'nota', 'registrado_por'];

    protected $casts = [
        'periodo' => 'date:Y-m-d',
        'valor' => 'decimal:2',
    ];

    /** Lo lee el Observer (afterCommit): ahí ya se perdió el valor original. */
    public bool $valorCambio = false;

    protected static function booted(): void
    {
        // Numérico: "50" y "50.00" son el mismo valor aunque Eloquent los vea distintos.
        static::saving(function (self $registro) {
            $registro->valorCambio = ! $registro->exists || (float) $registro->getOriginal('valor') !== (float) $registro->valor;
        });
    }

    public function metrica(): BelongsTo
    {
        return $this->belongsTo(MetricaCliente::class, 'metrica_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
