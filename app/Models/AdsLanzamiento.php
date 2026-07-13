<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsLanzamiento extends Model
{
    use HasFactory;

    protected $table = 'ads_lanzamiento';

    public const CHECKLIST = [
        'campana_lanzada' => 'Campaña lanzada en plataforma',
        'primera_semana_registrada' => 'Primera semana de datos registrada',
        'primera_optimizacion' => 'Primera optimización realizada',
        'cliente_informado' => 'Cliente informado del avance',
        'metricas_mes_registradas' => 'Métricas del mes registradas',
        'roas_objetivo_alcanzado' => 'ROAS objetivo alcanzado',
    ];

    protected $fillable = [
        'ads_campana_id',
        'ciclo',
        'fecha_lanzamiento',
        'porcentaje_avance',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'fecha_lanzamiento' => 'date',
        'porcentaje_avance' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
