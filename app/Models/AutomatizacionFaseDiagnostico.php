<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatizacionFaseDiagnostico extends Model
{
    use HasFactory;

    protected $table = 'automatizacion_fase_diagnosticos';

    public const CHECKLIST = [
        'objetivo_definido' => 'Objetivo del cliente definido',
        'procesos_actuales_mapeados' => 'Procesos actuales mapeados',
        'herramientas_actuales_identificadas' => 'Herramientas actuales identificadas',
        'viabilidad_confirmada' => 'Viabilidad de automatización confirmada',
        'propuesta_enviada' => 'Propuesta enviada',
        'propuesta_aprobada_cliente' => 'Propuesta aprobada por el cliente',
    ];

    protected $fillable = [
        'proyecto_id',
        'ciclo',
        'objetivo_cliente',
        'procesos_actuales',
        'herramientas_actuales',
        'volumen_mensual_estimado',
        'viable',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'volumen_mensual_estimado' => 'integer',
        'viable' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionProyecto::class, 'proyecto_id');
    }
}
