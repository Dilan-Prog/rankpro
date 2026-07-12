<?php

namespace App\Models;

use App\Enums\EstadoCampana;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'fecha_inicio',
        'fecha_fin',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoCampana::class,
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

    /**
     * metricas/creativos are true children (onDelete('cascade') in the
     * migration) — mirror that at the model level since a soft-delete
     * doesn't trigger the DB-level cascade.
     */
    protected static function booted(): void
    {
        static::deleting(function (AdsCampana $campana) {
            $campana->metricas()->delete();
            $campana->creativos()->delete();
        });
    }
}
