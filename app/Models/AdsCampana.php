<?php

namespace App\Models;

use App\Enums\EstadoCampana;
use App\Enums\FaseAds;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdsCampana extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ads_campanas';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'nombre',
        'plataforma',
        'objetivo',
        'presupuesto_mensual',
        'estado',
        'fase_actual',
        'ciclo_actual',
        'fecha_inicio',
        'fecha_fin',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoCampana::class,
        'fase_actual' => FaseAds::class,
        'ciclo_actual' => 'integer',
        'presupuesto_mensual' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function metricas(): HasMany
    {
        return $this->hasMany(AdsMetrica::class);
    }

    public function creativos(): HasMany
    {
        return $this->hasMany(AdsCreativo::class);
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(AdsGrupo::class);
    }

    public function optimizaciones(): HasMany
    {
        return $this->hasMany(AdsOptimizacion::class)->orderByDesc('fecha');
    }

    /**
     * briefing/configuracion/lanzamiento/reporte are cycle-scoped (one row
     * per ciclo_actual) so "Nuevo Ciclo" archives history. The *Actual
     * relations resolve to the current cycle's row via ofMany.
     */
    public function briefings(): HasMany
    {
        return $this->hasMany(AdsBriefing::class)->orderByDesc('ciclo');
    }

    public function briefing(): HasOne
    {
        return $this->hasOne(AdsBriefing::class)->ofMany('ciclo', 'max');
    }

    public function configuraciones(): HasMany
    {
        return $this->hasMany(AdsConfiguracion::class)->orderByDesc('ciclo');
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(AdsConfiguracion::class)->ofMany('ciclo', 'max');
    }

    public function lanzamientos(): HasMany
    {
        return $this->hasMany(AdsLanzamiento::class)->orderByDesc('ciclo');
    }

    public function lanzamiento(): HasOne
    {
        return $this->hasOne(AdsLanzamiento::class)->ofMany('ciclo', 'max');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(AdsReporte::class)->orderByDesc('ciclo');
    }

    public function reporteActual(): HasOne
    {
        return $this->hasOne(AdsReporte::class)->ofMany('ciclo', 'max');
    }

    /**
     * All children are hard-delete tables with onDelete('cascade') at the
     * DB level, which doesn't fire on a soft delete — mirror it here.
     */
    protected static function booted(): void
    {
        static::deleting(function (AdsCampana $campana) {
            $campana->metricas()->delete();
            $campana->creativos()->delete();
            $campana->grupos()->delete();
            $campana->optimizaciones()->delete();
            $campana->briefings()->delete();
            $campana->configuraciones()->delete();
            $campana->lanzamientos()->delete();
            $campana->reportes()->delete();
        });
    }
}
