<?php

namespace App\Models;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoCobro;
use App\Enums\TipoServicio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'tipo_cobro',
        'precio_mensual',
        'estado',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'tipo' => TipoServicio::class,
        'tipo_cobro' => TipoCobro::class,
        'estado' => EstadoClienteServicio::class,
        'precio_mensual' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * ÚNICA definición de "ingreso recurrente": servicio activo y de cobro mensual. Todo el MRR del
     * sistema sale de aquí (vía FinanzasMetrics); no repetir esta condición en otro lado.
     * Se compara con `->value` en SQL: contra el string ya hidratado un enum nunca iguala con `==`.
     */
    public function scopeMensual(Builder $query): Builder
    {
        return $query->where('estado', EstadoClienteServicio::Activo->value)
            ->where('tipo_cobro', TipoCobro::Mensual->value);
    }

    /** Espejo en memoria de scopeMensual() para colecciones ya cargadas (comparando enums, no strings). */
    public function aportaMrr(): bool
    {
        return $this->estado === EstadoClienteServicio::Activo
            && ($this->tipo_cobro ?? TipoCobro::Mensual) === TipoCobro::Mensual;
    }

    /**
     * Servicio mensual que sigue activo pero cuya fecha_fin ya pasó: se avisa, no se saca del
     * MRR (decide el usuario si renueva o cierra). En un pago único o gratuito la fecha_fin es
     * la entrega del proyecto, no un contrato que renovar, así que no cuenta como "vencido".
     */
    public function estaVencido(): bool
    {
        return $this->estado === EstadoClienteServicio::Activo
            && ($this->tipo_cobro ?? TipoCobro::Mensual) === TipoCobro::Mensual
            && $this->fecha_fin !== null
            && $this->fecha_fin->lt(now()->startOfDay());
    }

    /** Texto de precio según el tipo de cobro, para listas de selección y documentos. */
    public function etiquetaPrecio(): string
    {
        $monto = '$' . number_format((float) $this->precio_mensual);

        return match ($this->tipo_cobro ?? TipoCobro::Mensual) {
            TipoCobro::Mensual => $monto . '/mes',
            TipoCobro::Unico => $monto . ' pago único',
            TipoCobro::Gratuito => 'Gratuito',
        };
    }

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
