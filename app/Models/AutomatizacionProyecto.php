<?php

namespace App\Models;

use App\Enums\EstadoCampana;
use App\Enums\FaseAutomatizacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomatizacionProyecto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'automatizacion_proyectos';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'nombre',
        'estado',
        'fase_actual',
        'ciclo_actual',
        'notas',
        'fecha_inicio',
    ];

    protected $casts = [
        'estado' => EstadoCampana::class,
        'fase_actual' => FaseAutomatizacion::class,
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

    /** Los flujos individuales (construidos en n8n) — hijos reales del proyecto, no cycle-scoped. */
    public function flujos(): HasMany
    {
        return $this->hasMany(AutomatizacionFlujo::class, 'proyecto_id');
    }

    /**
     * diagnostico/diseno/implementacion/reporte son cycle-scoped (una fila
     * por ciclo_actual) para que "Nuevo Ciclo" archive el historial en vez
     * de sobrescribirlo. Las relaciones *Actual resuelven a la fila del
     * ciclo vigente vía el patrón "greatest-n-per-group" de MySQL (ofMany).
     */
    public function diagnosticos(): HasMany
    {
        return $this->hasMany(AutomatizacionFaseDiagnostico::class, 'proyecto_id')->orderByDesc('ciclo');
    }

    public function faseDiagnostico(): HasOne
    {
        return $this->hasOne(AutomatizacionFaseDiagnostico::class, 'proyecto_id')->ofMany('ciclo', 'max');
    }

    public function disenos(): HasMany
    {
        return $this->hasMany(AutomatizacionFaseDiseno::class, 'proyecto_id')->orderByDesc('ciclo');
    }

    public function faseDiseno(): HasOne
    {
        return $this->hasOne(AutomatizacionFaseDiseno::class, 'proyecto_id')->ofMany('ciclo', 'max');
    }

    public function implementaciones(): HasMany
    {
        return $this->hasMany(AutomatizacionFaseImplementacion::class, 'proyecto_id')->orderByDesc('ciclo');
    }

    public function faseImplementacion(): HasOne
    {
        return $this->hasOne(AutomatizacionFaseImplementacion::class, 'proyecto_id')->ofMany('ciclo', 'max');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(AutomatizacionReporte::class, 'proyecto_id')->orderByDesc('ciclo');
    }

    public function reporteActual(): HasOne
    {
        return $this->hasOne(AutomatizacionReporte::class, 'proyecto_id')->ofMany('ciclo', 'max');
    }

    protected static function booted(): void
    {
        static::deleting(function (AutomatizacionProyecto $proyecto) {
            $proyecto->flujos()->delete();
            $proyecto->diagnosticos()->delete();
            $proyecto->disenos()->delete();
            $proyecto->implementaciones()->delete();
            $proyecto->reportes()->delete();
        });
    }
}
