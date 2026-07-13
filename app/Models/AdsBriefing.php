<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsBriefing extends Model
{
    use HasFactory;

    protected $table = 'ads_briefing';

    public const CHECKLIST = [
        'briefing_completado' => 'Briefing completado con el cliente',
        'objetivo_definido' => 'Objetivo de campaña definido',
        'presupuesto_aprobado' => 'Presupuesto aprobado',
        'audiencia_definida' => 'Audiencia objetivo definida',
        'propuesta_valor_aprobada' => 'Propuesta de valor aprobada',
        'landing_lista' => 'Landing page lista',
    ];

    protected $fillable = [
        'ads_campana_id',
        'ciclo',
        'publico_objetivo',
        'rango_edad',
        'genero',
        'ubicacion_geografica',
        'intereses',
        'propuesta_valor',
        'analisis_competencia',
        'producto_servicio',
        'url_destino',
        'fecha_inicio_estimada',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'fecha_inicio_estimada' => 'date',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
