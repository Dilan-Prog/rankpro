<?php

namespace App\Models;

use App\Enums\EstadoOnPageAccion;
use App\Enums\TipoActividadSeo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acción on-page de una campaña SEO y, a la vez, «actividad» del SEO Plan.
 * Cuelga de la campaña; el cliente se alcanza por ella (ver SeoPlanController).
 */
class SeoOnPageAccion extends Model
{
    use HasFactory;

    protected $table = 'seo_onpage_acciones';

    protected $fillable = [
        'seo_campana_id',
        'tipo',
        'cluster_id',
        'url_pagina',
        'accion',
        'fecha',
        'fecha_limite',
        'cerrada_en',
        'medicion_antes',
        'medicion_despues',
        'orden',
        'responsable_id',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_limite' => 'date',
        'cerrada_en' => 'date',
        'orden' => 'integer',
        'tipo' => TipoActividadSeo::class,
        'estado' => EstadoOnPageAccion::class,
    ];

    protected static function booted(): void
    {
        // La fecha de cierre se deriva del estado aquí y no en cada controlador:
        // el módulo SEO original también marca acciones como completadas, y el
        // resumen «a tiempo / tarde» del SEO Plan no debe depender de por dónde se cerró.
        static::saving(function (self $accion) {
            if (! $accion->isDirty('estado')) {
                return;
            }

            if ($accion->estado === EstadoOnPageAccion::Completada) {
                $accion->cerrada_en ??= today();
            } else {
                $accion->cerrada_en = null;
            }
        });
    }

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(SeoActividadNota::class, 'seo_onpage_accion_id')->orderByDesc('id');
    }

    /** Abierta y con la fecha límite ya pasada. */
    public function estaVencida(): bool
    {
        return $this->estado !== EstadoOnPageAccion::Completada
            && $this->fecha_limite !== null
            && $this->fecha_limite->lt(today());
    }
}
