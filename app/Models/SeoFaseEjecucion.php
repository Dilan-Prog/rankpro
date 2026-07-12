<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoFaseEjecucion extends Model
{
    use HasFactory;

    protected $table = 'seo_fase_ejecucion';

    public const CHECKLIST = [
        'on_page_completado' => 'On-Page completado',
        'off_page_completado' => 'Off-Page completado',
        'tecnico_completado' => 'Técnico completado',
        'contenido_completado' => 'Contenido completado',
        'posiciones_actualizadas' => 'Seguimiento de posiciones actualizado',
        'backlinks_actualizados' => 'Backlinks actualizados',
    ];

    protected $fillable = [
        'seo_campana_id',
        'on_page_completado',
        'off_page_completado',
        'tecnico_completado',
        'contenido_completado',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'on_page_completado' => 'boolean',
        'off_page_completado' => 'boolean',
        'tecnico_completado' => 'boolean',
        'contenido_completado' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
