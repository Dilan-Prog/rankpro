<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatizacionFaseDiseno extends Model
{
    use HasFactory;

    protected $table = 'automatizacion_fase_disenos';

    public const CHECKLIST = [
        'flujos_priorizados' => 'Flujos priorizados',
        'integraciones_confirmadas' => 'Integraciones confirmadas',
        'diagrama_flujo_listo' => 'Diagrama de flujo listo',
        'casos_prueba_definidos' => 'Casos de prueba definidos',
        'cronograma_definido' => 'Cronograma definido',
        'diseno_aprobado_cliente' => 'Diseño aprobado por el cliente',
    ];

    protected $fillable = [
        'proyecto_id',
        'ciclo',
        'flujos_planeados',
        'integraciones_planeadas',
        'diagrama_url',
        'cronograma',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionProyecto::class, 'proyecto_id');
    }
}
