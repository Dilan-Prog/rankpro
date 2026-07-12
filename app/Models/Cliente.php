<?php

namespace App\Models;

use App\Enums\EstadoClienteServicio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'empresa',
        'email',
        'telefono',
        'contacto_nombre',
        'estado',
        'fecha_inicio_contrato',
        'fecha_renovacion_contrato',
        'forma_pago',
        'metodo_pago',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoClienteServicio::class,
        'fecha_inicio_contrato' => 'date',
        'fecha_renovacion_contrato' => 'date',
    ];

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function seoCampanas(): HasMany
    {
        return $this->hasMany(SeoCampana::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function adsCampanas(): HasMany
    {
        return $this->hasMany(AdsCampana::class);
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    public function finanzas(): HasMany
    {
        return $this->hasMany(Finanza::class);
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    /**
     * Cascades a delete (soft or force) to every child relation. Children
     * that also use SoftDeletes (servicios, seoCampanas, adsCampanas,
     * proyectos) are deleted one-by-one via ->each->delete() rather than a
     * bulk relation delete() — a bulk delete is a single UPDATE/DELETE
     * query that skips Eloquent model events entirely, which would break
     * *their* own cascade hooks (e.g. servicio -> seoCampanas -> posiciones).
     * Leaf relations with no children of their own (keywords, finanzas,
     * archivos) are fine as a bulk delete.
     */
    protected static function booted(): void
    {
        static::deleting(function (Cliente $cliente) {
            $cliente->servicios()->get()->each->delete();
            $cliente->seoCampanas()->get()->each->delete();
            $cliente->adsCampanas()->get()->each->delete();
            $cliente->proyectos()->get()->each->delete();
            $cliente->keywords()->delete();
            $cliente->finanzas()->delete();
            $cliente->archivos()->delete();
        });
    }
}
