<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsReporte extends Model
{
    use HasFactory;

    protected $table = 'ads_reporte';

    public const CHECKLIST = [
        'metricas_finales_registradas' => 'Métricas finales registradas',
        'reporte_generado' => 'Reporte generado',
        'reporte_entregado_cliente' => 'Reporte entregado al cliente',
        'conclusiones_documentadas' => 'Conclusiones documentadas',
        'siguiente_ciclo_o_cierre_definido' => 'Siguiente ciclo definido o campaña cerrada',
        'cliente_firmo_conformidad' => 'Cliente firmó conformidad',
    ];

    protected $fillable = [
        'ads_campana_id',
        'ciclo',
        'inversion_total',
        'impresiones_total',
        'clics_total',
        'ctr_promedio',
        'conversiones_total',
        'roas_promedio',
        'cpl_promedio',
        'cpa_promedio',
        'mejor_anuncio_ctr',
        'mejor_anuncio_conversiones',
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
        'inversion_total' => 'decimal:2',
        'impresiones_total' => 'integer',
        'clics_total' => 'integer',
        'ctr_promedio' => 'decimal:3',
        'conversiones_total' => 'integer',
        'roas_promedio' => 'decimal:2',
        'cpl_promedio' => 'decimal:2',
        'cpa_promedio' => 'decimal:2',
        'satisfaccion_cliente' => 'integer',
        'continua_campana' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
