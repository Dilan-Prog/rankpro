<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoFaseAuditoria extends Model
{
    use HasFactory;

    protected $table = 'seo_fase_auditoria';

    public const CHECKLIST = [
        'core_web_vitals_revisado' => 'Core Web Vitals revisado',
        'errores_tecnicos_documentados' => 'Errores técnicos documentados',
        'indexacion_revisada' => 'Indexación revisada',
        'velocidad_evaluada' => 'Velocidad evaluada (mobile y desktop)',
        'sitemap_verificado' => 'Sitemap verificado',
        'robots_verificado' => 'Robots.txt verificado',
    ];

    protected $fillable = [
        'seo_campana_id',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
