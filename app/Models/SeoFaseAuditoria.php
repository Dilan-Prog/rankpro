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
        'auditoria_tecnica_completada' => 'Auditoría técnica completada',
        'core_web_vitals_registrados' => 'Core Web Vitals registrados',
        'errores_tecnicos_documentados' => 'Errores técnicos documentados',
        'indexacion_verificada' => 'Indexación verificada',
        'sitemap_robots_verificados' => 'Sitemap y robots.txt verificados',
        'reporte_entregado_cliente' => 'Reporte de auditoría entregado al cliente',
    ];

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'seo_score',
        'velocidad_mobile',
        'velocidad_desktop',
        'lcp_mobile',
        'fid_mobile',
        'cls_mobile',
        'lcp_desktop',
        'fid_desktop',
        'cls_desktop',
        'errores_tecnicos',
        'indexacion_ok',
        'sitemap_ok',
        'robots_ok',
        'errores_404',
        'redirecciones_incorrectas',
        'duplicidad_contenido',
        'canonical_ok',
        'schema_ok',
        'herramienta',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'seo_score' => 'integer',
        'velocidad_mobile' => 'decimal:2',
        'velocidad_desktop' => 'decimal:2',
        'lcp_mobile' => 'decimal:2',
        'fid_mobile' => 'decimal:2',
        'cls_mobile' => 'decimal:3',
        'lcp_desktop' => 'decimal:2',
        'fid_desktop' => 'decimal:2',
        'cls_desktop' => 'decimal:3',
        'errores_tecnicos' => 'integer',
        'indexacion_ok' => 'boolean',
        'sitemap_ok' => 'boolean',
        'robots_ok' => 'boolean',
        'errores_404' => 'integer',
        'redirecciones_incorrectas' => 'integer',
        'duplicidad_contenido' => 'boolean',
        'canonical_ok' => 'boolean',
        'schema_ok' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
