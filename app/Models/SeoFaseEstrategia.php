<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoFaseEstrategia extends Model
{
    use HasFactory;

    protected $table = 'seo_fase_estrategia';

    public const CHECKLIST = [
        'keywords_objetivo_definidas' => 'Keywords objetivo definidas',
        'competencia_analizada' => 'Análisis de competencia realizado',
        'plan_contenido_definido' => 'Plan de contenido definido',
        'estrategia_link_building_definida' => 'Estrategia de link building definida',
        'metas_mensuales_establecidas' => 'Metas mensuales establecidas',
    ];

    protected $fillable = [
        'seo_campana_id',
        'analisis_competencia',
        'plan_contenido',
        'link_building_strategy',
        'meta_trafico_mensual',
        'meta_posiciones_top10',
        'meta_leads_mensual',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'meta_trafico_mensual' => 'integer',
        'meta_posiciones_top10' => 'integer',
        'meta_leads_mensual' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
