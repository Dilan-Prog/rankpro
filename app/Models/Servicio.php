<?php

namespace App\Models;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoServicio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servicio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'responsable_id',
        'tipo',
        'nombre',
        'descripcion',
        'precio_mensual',
        'estado',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'tipo' => TipoServicio::class,
        'estado' => EstadoClienteServicio::class,
        'precio_mensual' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ServicioEvento::class)->latest('created_at');
    }

    public function seoCampanas(): HasMany
    {
        return $this->hasMany(SeoCampana::class);
    }

    public function adsCampanas(): HasMany
    {
        return $this->hasMany(AdsCampana::class);
    }

    public function finanzas(): HasMany
    {
        return $this->hasMany(Finanza::class);
    }

    /**
     * See Cliente::booted() for why ->each->delete() is used instead of a
     * bulk relation delete() for children that have cascades of their own.
     */
    protected static function booted(): void
    {
        static::deleting(function (Servicio $servicio) {
            $servicio->seoCampanas()->get()->each->delete();
            $servicio->adsCampanas()->get()->each->delete();
            $servicio->finanzas()->delete();
            $servicio->eventos()->delete();
        });
    }
}
