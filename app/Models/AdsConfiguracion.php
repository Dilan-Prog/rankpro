<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'ads_configuracion';

    public const CHECKLIST = [
        'estructura_creada' => 'Estructura de campaña creada en plataforma',
        'grupos_configurados' => 'Grupos de anuncios configurados',
        'creativos_aprobados' => 'Creativos subidos y aprobados',
        'pixel_verificado' => 'Píxel/conversiones verificados',
        'utms_configurados' => 'UTMs configurados',
        'revision_final' => 'Revisión final antes de lanzar',
    ];

    protected $fillable = [
        'ads_campana_id',
        'ciclo',
        'estructura_campana',
        'pixel_ok',
        'cuenta_publicitaria',
        'utms_ok',
        'notas',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'pixel_ok' => 'boolean',
        'utms_ok' => 'boolean',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
