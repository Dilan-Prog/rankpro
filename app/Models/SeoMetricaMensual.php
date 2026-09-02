<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMetricaMensual extends Model
{
    use HasFactory;

    protected $table = 'seo_metricas_mensuales';

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'mes',
        'anio',
        'trafico_organico',
        'keywords_top3',
        'keywords_top10',
        'keywords_top100',
        'backlinks_total',
        'errores_resueltos',
        'errores_pendientes',
        'notas',
    ];

    protected $casts = [
        'mes' => 'integer',
        'anio' => 'integer',
        'trafico_organico' => 'integer',
        'keywords_top3' => 'integer',
        'keywords_top10' => 'integer',
        'keywords_top100' => 'integer',
        'backlinks_total' => 'integer',
        'errores_resueltos' => 'integer',
        'errores_pendientes' => 'integer',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
