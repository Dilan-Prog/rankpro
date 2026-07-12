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
        'seo_score',
        'trafico_organico_mensual',
        'backlinks_total',
        'errores_tecnicos',
        'velocidad_mobile',
        'velocidad_desktop',
        'sitemap_ok',
        'robots_ok',
        'notas',
        'fecha_inicio',
    ];

    protected $casts = [
        'estado' => EstadoCampana::class,
        'fase_actual' => FaseSeo::class,
        'ciclo_actual' => 'integer',
        'sitemap_ok' => 'boolean',
        'robots_ok' => 'boolean',
        'velocidad_mobile' => 'decimal:2',
        'velocidad_desktop' => 'decimal:2',
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
        return $this->hasMany(Backlink::class);
    }

    public function faseAuditoria(): HasOne
    {
        return $this->hasOne(SeoFaseAuditoria::class);
    }

    public function faseEstrategia(): HasOne
    {
        return $this->hasOne(SeoFaseEstrategia::class);
    }

    public function faseEjecucion(): HasOne
    {
        return $this->hasOne(SeoFaseEjecucion::class);
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
     * posiciones/backlinks are true children (onDelete('cascade') in the
     * migration) so they're deleted with the campaign. keywords.campana_id
     * is nullable with nullOnDelete() instead — a keyword can outlive its
     * campaign — so those are just unassigned, not deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (SeoCampana $campana) {
            $campana->posiciones()->delete();
            $campana->backlinks()->delete();
            $campana->keywords()->update(['campana_id' => null]);
            $campana->faseAuditoria()->delete();
            $campana->faseEstrategia()->delete();
            $campana->faseEjecucion()->delete();
            $campana->reportes()->delete();
        });
    }
}
