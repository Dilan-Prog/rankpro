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

    /**
     * Informational technical-SEO tracking, grouped by category — fully
     * independent of CHECKLIST above (which gates phase approval via
     * SeoFaseController::aprobar()). Stored in the separate
     * `tecnico_checklist` column; never read by checklistCompleto().
     */
    public const TECNICO_CHECKLIST = [
        'Indexación y Rastreo' => [
            'sitemap_enviado_gsc' => 'Sitemap XML enviado a Google Search Console',
            'robots_sin_bloqueos' => 'Robots.txt sin bloqueos indebidos',
            'canonicals_correctos' => 'Etiquetas canonical correctas en todas las páginas',
            'paginas_huerfanas_eliminadas' => 'Páginas huérfanas eliminadas o enlazadas',
            'errores_404_corregidos' => 'Errores 404 corregidos',
        ],
        'Velocidad y Rendimiento' => [
            'imagenes_webp' => 'Imágenes convertidas a WebP',
            'lazy_loading' => 'Lazy loading implementado',
            'cdn_activo' => 'CDN activo',
            'cache_navegador' => 'Cache del navegador configurado',
            'css_js_minificados' => 'CSS y JS minificados',
        ],
        'Datos Estructurados' => [
            'schema_organization' => 'Schema Organization implementado',
            'schema_localbusiness' => 'Schema LocalBusiness implementado',
            'schema_faqpage' => 'Schema FAQPage implementado',
            'schema_review' => 'Schema Review implementado',
            'schema_breadcrumblist' => 'Schema BreadcrumbList implementado',
        ],
        'Mobile y UX' => [
            'diseno_responsive' => 'Diseño responsive verificado',
            'botones_touch_friendly' => 'Botones táctiles de tamaño adecuado',
            'fuentes_legibles' => 'Fuentes legibles en móvil',
            'sin_overflow_horizontal' => 'Sin desbordamiento horizontal',
            'cwv_verde_movil' => 'Core Web Vitals en verde (móvil)',
        ],
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
        'tecnico_checklist',
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
        'tecnico_checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
