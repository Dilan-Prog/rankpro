<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatizacionReporte extends Model
{
    use HasFactory;

    protected $table = 'automatizacion_reportes';

    public const CHECKLIST = [
        'metricas_registradas' => 'Métricas registradas',
        'flujos_funcionando_correctamente' => 'Flujos funcionando correctamente',
        'cliente_informado_resultados' => 'Cliente informado de los resultados',
        'ajustes_documentados' => 'Ajustes documentados',
        'siguiente_ciclo_o_cierre_definido' => 'Siguiente ciclo definido o proyecto cerrado',
        'cliente_satisfecho' => 'Cliente satisfecho',
    ];

    protected $fillable = [
        'proyecto_id',
        'ciclo',
        'flujos_activos_total',
        'horas_ahorradas_mes',
        'mensajes_gestionados_mes',
        'tareas_automatizadas_mes',
        'incidencias',
        'conclusiones',
        'recomendaciones',
        'satisfaccion_cliente',
        'continua_proyecto',
        'notas_cierre',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'flujos_activos_total' => 'integer',
        'horas_ahorradas_mes' => 'decimal:2',
        'mensajes_gestionados_mes' => 'integer',
        'tareas_automatizadas_mes' => 'integer',
        'satisfaccion_cliente' => 'integer',
        'continua_proyecto' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionProyecto::class, 'proyecto_id');
    }
}
