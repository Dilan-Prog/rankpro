<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoPlaneacion extends Model
{
    use HasFactory;

    protected $table = 'proyecto_planeacion';

    public const CHECKLIST = [
        'briefing_completado' => 'Briefing completado',
        'objetivos_definidos' => 'Objetivos definidos',
        'requerimientos_documentados' => 'Requerimientos documentados',
        'presupuesto_aprobado' => 'Presupuesto aprobado por cliente',
        'contrato_firmado' => 'Contrato firmado',
        'anticipo_recibido' => 'Anticipo recibido',
    ];

    protected $fillable = [
        'proyecto_id',
        'objetivos',
        'requerimientos_funcionales',
        'requerimientos_tecnicos',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
