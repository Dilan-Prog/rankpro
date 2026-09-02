<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatizacionFaseImplementacion extends Model
{
    use HasFactory;

    protected $table = 'automatizacion_fase_implementaciones';

    public const CHECKLIST = [
        'flujos_construidos_n8n' => 'Flujos construidos en n8n',
        'pruebas_realizadas' => 'Pruebas realizadas',
        'datos_prueba_verificados' => 'Datos de prueba verificados',
        'cliente_capacitado' => 'Cliente capacitado',
        'flujo_en_produccion' => 'Flujo en producción',
        'monitoreo_configurado' => 'Monitoreo configurado',
    ];

    protected $fillable = [
        'proyecto_id',
        'ciclo',
        'porcentaje_avance',
        'flujos_construidos',
        'pruebas_realizadas',
        'cliente_capacitado',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'porcentaje_avance' => 'integer',
        'flujos_construidos' => 'integer',
        'pruebas_realizadas' => 'boolean',
        'cliente_capacitado' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionProyecto::class, 'proyecto_id');
    }
}
