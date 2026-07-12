<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoFaseEstrategia extends Model
{
    use HasFactory;

    protected $table = 'seo_fase_estrategia';

    public const CHECKLIST = [
        'keywords_objetivo_definidas' => 'Keywords objetivo definidas',
        'competencia_analizada' => 'Análisis de competencia completado',
        'plan_contenido_aprobado' => 'Plan de contenido aprobado',
        'link_building_definido' => 'Estrategia de link building definida',
        'metas_mensuales_establecidas' => 'Metas mensuales establecidas',
        'estrategia_aprobada_cliente' => 'Estrategia aprobada por cliente',
    ];

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'keywords_ids',
        'analisis_competencia',
        'plan_contenido',
        'estrategia_link_building',
        'meta_trafico_mensual',
        'meta_top3',
        'meta_top10',
        'meta_leads_mensual',
        'herramientas',
        'cronograma',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'keywords_ids' => 'array',
        'meta_trafico_mensual' => 'integer',
        'meta_top3' => 'integer',
        'meta_top10' => 'integer',
        'meta_leads_mensual' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }

    /** Resolves keywords_ids (array of IDs into the shared keywords bank) into actual Keyword models. */
    public function keywords(): Collection
    {
        return Keyword::whereIn('id', $this->keywords_ids ?? [])->get();
    }
}
