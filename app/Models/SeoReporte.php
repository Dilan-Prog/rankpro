<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoReporte extends Model
{
    use HasFactory;

    protected $table = 'seo_reportes';

    public const CHECKLIST = [
        'resultados_comparados' => 'Resultados comparados contra metas',
        'trafico_organico_registrado' => 'Tráfico orgánico registrado',
        'posiciones_ganadas_registradas' => 'Posiciones ganadas registradas',
        'roas_organico_calculado' => 'ROAS orgánico calculado',
        'cliente_informado' => 'Cliente informado del resultado del ciclo',
    ];

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'resultados_vs_metas',
        'trafico_organico_final',
        'posiciones_ganadas',
        'roas_organico',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'trafico_organico_final' => 'integer',
        'posiciones_ganadas' => 'integer',
        'roas_organico' => 'decimal:2',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
