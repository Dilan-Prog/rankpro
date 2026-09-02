<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatizacionFlujo extends Model
{
    use HasFactory;

    protected $table = 'automatizacion_flujos';

    protected $fillable = [
        'proyecto_id',
        'nombre',
        'tipo',
        'complejidad',
        'integraciones',
        'horas_ahorradas_mes',
        'mensajes_gestionados_mes',
        'estado',
        'fecha_implementado',
        'notas',
    ];

    protected $casts = [
        'integraciones' => 'array',
        'horas_ahorradas_mes' => 'decimal:2',
        'mensajes_gestionados_mes' => 'integer',
        'fecha_implementado' => 'date',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(AutomatizacionProyecto::class, 'proyecto_id');
    }
}
