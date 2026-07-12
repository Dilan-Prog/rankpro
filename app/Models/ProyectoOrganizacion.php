<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoOrganizacion extends Model
{
    use HasFactory;

    protected $table = 'proyecto_organizacion';

    public const CHECKLIST = [
        'stack_definido' => 'Stack definido',
        'repositorio_creado' => 'Repositorio creado',
        'staging_configurado' => 'Entorno de staging configurado',
        'equipo_asignado' => 'Equipo asignado',
        'arquitectura_documentada' => 'Arquitectura documentada',
        'diseno_figma_aprobado' => 'Diseño en Figma aprobado',
    ];

    protected $fillable = [
        'proyecto_id',
        'stack_tecnologico',
        'arquitectura',
        'herramientas',
        'url_repositorio',
        'url_staging',
        'equipo',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'equipo' => 'array',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
