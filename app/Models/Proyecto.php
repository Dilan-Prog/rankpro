<?php

namespace App\Models;

use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Enums\FormaPagoProyecto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proyecto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'nombre',
        'tipo',
        'descripcion',
        'fase_actual',
        'porcentaje_avance',
        'presupuesto',
        'anticipo',
        'pagos_recibidos',
        'forma_pago',
        'fecha_inicio',
        'fecha_entrega_estimada',
        'fecha_entrega_real',
        'responsable',
        'estado',
    ];

    protected $casts = [
        'fase_actual' => FaseProyecto::class,
        'estado' => EstadoProyecto::class,
        'forma_pago' => FormaPagoProyecto::class,
        'presupuesto' => 'decimal:2',
        'anticipo' => 'decimal:2',
        'pagos_recibidos' => 'decimal:2',
        'porcentaje_avance' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_entrega_estimada' => 'date',
        'fecha_entrega_real' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function planeacion(): HasOne
    {
        return $this->hasOne(ProyectoPlaneacion::class);
    }

    public function organizacion(): HasOne
    {
        return $this->hasOne(ProyectoOrganizacion::class);
    }

    public function direccion(): HasOne
    {
        return $this->hasOne(ProyectoDireccion::class);
    }

    public function control(): HasOne
    {
        return $this->hasOne(ProyectoControl::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class);
    }

    public function comunicaciones(): HasMany
    {
        return $this->hasMany(ProyectoComunicacion::class);
    }

    public function qa(): HasMany
    {
        return $this->hasMany(ProyectoQa::class);
    }

    /**
     * Tareas/Bugs/comunicaciones/qa don't use SoftDeletes, so a soft-deleted
     * proyecto would otherwise leave them orphaned (the DB's ON DELETE
     * CASCADE only fires on a real DELETE, not the UPDATE a soft-delete
     * performs). The 4 phase records are true 1:1 children with their own
     * onDelete('cascade') at the DB level, so they don't need a hook here.
     */
    protected static function booted(): void
    {
        static::deleting(function (Proyecto $proyecto) {
            $proyecto->tareas()->delete();
            $proyecto->bugs()->delete();
            $proyecto->comunicaciones()->delete();
            $proyecto->qa()->delete();
        });
    }
}
