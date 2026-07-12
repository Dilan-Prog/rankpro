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
        'backlinks_mes_conseguidos' => 'Backlinks del mes conseguidos',
        'tecnico_corregido' => 'Técnico corregido',
        'contenido_publicado' => 'Contenido publicado',
        'posiciones_registradas' => 'Posiciones registradas',
        'cliente_informado_avance' => 'Cliente informado del avance',
    ];

    protected $fillable = [
        'seo_campana_id',
        'ciclo',
        'porcentaje_avance',
        'paginas_optimizadas',
        'titles_meta_ok',
        'headings_ok',
        'imagenes_ok',
        'links_internos_ok',
        'backlinks_mes',
        'errores_404_ok',
        'redirecciones_ok',
        'schema_ok',
        'velocidad_ok',
        'articulos_publicados',
        'checklist',
        'aprobado',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'ciclo' => 'integer',
        'porcentaje_avance' => 'integer',
        'paginas_optimizadas' => 'integer',
        'titles_meta_ok' => 'boolean',
        'headings_ok' => 'boolean',
        'imagenes_ok' => 'boolean',
        'links_internos_ok' => 'boolean',
        'backlinks_mes' => 'integer',
        'errores_404_ok' => 'boolean',
        'redirecciones_ok' => 'boolean',
        'schema_ok' => 'boolean',
        'velocidad_ok' => 'boolean',
        'articulos_publicados' => 'integer',
        'checklist' => 'array',
        'aprobado' => 'boolean',
        'fecha_aprobacion' => 'datetime',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
