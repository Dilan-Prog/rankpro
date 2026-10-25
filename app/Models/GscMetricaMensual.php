<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Totales de un mes de Search Console para una propiedad (sin desglose por query). */
class GscMetricaMensual extends Model
{
    protected $table = 'gsc_metricas_mensuales';

    protected $fillable = ['propiedad_id', 'periodo', 'clics', 'impresiones', 'ctr', 'posicion_media', 'parcial'];

    protected $casts = [
        'periodo' => 'date',
        'clics' => 'integer',
        'impresiones' => 'integer',
        'ctr' => 'float',
        'posicion_media' => 'float',
        'parcial' => 'boolean',
    ];

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(GscPropiedad::class, 'propiedad_id');
    }
}
