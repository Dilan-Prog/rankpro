<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoReporte extends Model
{
    use HasFactory;

    protected $table = 'seo_reportes';

    public const CHECKLIST = [
        'metricas_finales_registradas' => 'Métricas finales registradas',
        'reporte_generado' => 'Reporte generado',
        'reporte_entregado_cliente' => 'Reporte entregado al cliente',
        'conclusiones_documentadas' => 'Conclusiones documentadas',
        'siguiente_ciclo_o_cierre_definido' => 'Siguiente ciclo definido o campaña cerrada',
        'cliente_firmo_conformidad' => 'Cliente firmó conformidad',
    ];

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'trafico_inicio',
        'trafico_actual',
        'keywords_top3',
        'keywords_top10',
        'keywords_top100',
        'backlinks_total',
        'articulos_total',
        'errores_resueltos',
        'errores_pendientes',
        'conclusiones',
        'recomendaciones',
        'satisfaccion_cliente',
        'continua_campana',
        'notas_cierre',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'trafico_inicio' => 'integer',
        'trafico_actual' => 'integer',
        'keywords_top3' => 'integer',
        'keywords_top10' => 'integer',
        'keywords_top100' => 'integer',
        'backlinks_total' => 'integer',
        'articulos_total' => 'integer',
        'errores_resueltos' => 'integer',
        'errores_pendientes' => 'integer',
        'satisfaccion_cliente' => 'integer',
        'continua_campana' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
