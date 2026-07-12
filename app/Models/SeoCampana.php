<?php

namespace App\Models;

use App\Enums\EstadoCampana;
use App\Enums\FaseSeo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoCampana extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'seo_campanas';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'nombre',
        'url_sitio',
        'estado',
        'fase_actual',
        'ciclo_actual',
        'notas',
        'fecha_inicio',
    ];

    protected $casts = [
        'estado' => EstadoCampana::class,
        'fase_actual' => FaseSeo::class,
        'ciclo_actual' => 'integer',
        'fecha_inicio' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function posiciones(): HasMany
    {
        return $this->hasMany(SeoPosicion::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'campana_id');
    }

    public function backlinks(): HasMany
    {
        return $this->hasMany(SeoBacklink::class);
    }

    public function contenido(): HasMany
    {
        return $this->hasMany(SeoContenido::class);
    }

    /**
     * auditoria/estrategia/ejecucion/reporte are all cycle-scoped (one row
     * per ciclo_actual) so "Nuevo Ciclo" archives history instead of
     * overwriting it. These *Actual relations resolve to the current
     * cycle's row via MySQL's "greatest-n-per-group" pattern (ofMany).
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(SeoFaseAuditoria::class)->orderByDesc('ciclo');
    }

    public function faseAuditoria(): HasOne
    {
        return $this->hasOne(SeoFaseAuditoria::class)->ofMany('ciclo', 'max');
    }

    public function estrategias(): HasMany
    {
        return $this->hasMany(SeoFaseEstrategia::class)->orderByDesc('ciclo');
    }

    public function faseEstrategia(): HasOne
    {
        return $this->hasOne(SeoFaseEstrategia::class)->ofMany('ciclo', 'max');
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(SeoFaseEjecucion::class)->orderByDesc('ciclo');
    }

    public function faseEjecucion(): HasOne
    {
        return $this->hasOne(SeoFaseEjecucion::class)->ofMany('ciclo', 'max');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(SeoReporte::class)->orderByDesc('ciclo');
    }

    public function reporteActual(): HasOne
    {
        return $this->hasOne(SeoReporte::class)->ofMany('ciclo', 'max');
    }

    /**
     * posiciones/backlinks/contenido are true children (onDelete('cascade')
     * in the migration) so they're deleted with the campaign. keywords are
     * only referenced by ID from seo_fase_estrategia.keywords_ids — they
     * live in the shared bank and are never touched here.
     */
    protected static function booted(): void
    {
        static::deleting(function (SeoCampana $campana) {
            $campana->posiciones()->delete();
            $campana->backlinks()->delete();
            $campana->contenido()->delete();
            $campana->auditorias()->delete();
            $campana->estrategias()->delete();
            $campana->ejecuciones()->delete();
            $campana->reportes()->delete();
        });
    }
}
