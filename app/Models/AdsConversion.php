<?php

namespace App\Models;

use App\Enums\EstadoConversion;
use App\Enums\TipoConversion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdsConversion extends Model
{
    use HasFactory;

    protected $table = 'ads_conversiones';

    protected $fillable = [
        'cliente_id',
        'ads_clic_id',
        'duplicada_de_id',
        'duplicada_at',
        'ads_campana_id',
        'visitor_id',
        'evento_id',
        'gclid',
        'gbraid',
        'wbraid',
        'tipo',
        'valor',
        'moneda',
        'estado',
        'exportada_at',
        'metadata',
        'ads_embudo_etapa_id',
        'datos_personalizados',
    ];

    protected $casts = [
        'tipo' => TipoConversion::class,
        'valor' => 'decimal:2',
        'estado' => EstadoConversion::class,
        'exportada_at' => 'datetime',
        'duplicada_at' => 'datetime',
        'metadata' => 'array',
        'datos_personalizados' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function adsClic(): BelongsTo
    {
        return $this->belongsTo(AdsClic::class, 'ads_clic_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(AdsEmbudoEtapa::class, 'ads_embudo_etapa_id');
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class, 'ads_campana_id');
    }

    /** La conversión que se conservó cuando esta se marcó como repetida. */
    public function duplicadaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicada_de_id');
    }

    public function duplicadas(): HasMany
    {
        return $this->hasMany(self::class, 'duplicada_de_id');
    }

    public function scopeNoDuplicadas(Builder $query): Builder
    {
        return $query->whereNull('duplicada_de_id');
    }

    public function scopeSoloDuplicadas(Builder $query): Builder
    {
        return $query->whereNotNull('duplicada_de_id');
    }

    public function esDuplicada(): bool
    {
        return $this->duplicada_de_id !== null;
    }

    /** gclid, si no gbraid, si no wbraid: lo único que Google Ads acepta para atribuir una conversión offline. */
    public function identificadorClic(): ?string
    {
        return $this->gclid ?: ($this->gbraid ?: ($this->wbraid ?: null));
    }

    /** Campaña propia (copiada al registrar) o, en las anteriores a esa columna, la del clic. */
    public function campanaNombre(): ?string
    {
        return $this->campana?->nombre ?? $this->adsClic?->adsCampana?->nombre;
    }

    /**
     * Por qué NO se puede subir a Google Ads (null = exportable). El orden va de lo irremediable a lo
     * corregible: sin identificador no hay nada que hacer; sin etapa el usuario solo tiene que clasificarla.
     *
     * @return 'duplicada'|'sin_identificador'|'sin_etapa'|null
     */
    public function motivoNoExportable(): ?string
    {
        if ($this->esDuplicada()) {
            return 'duplicada';
        }

        if ($this->identificadorClic() === null) {
            return 'sin_identificador';
        }

        return $this->ads_embudo_etapa_id === null ? 'sin_etapa' : null;
    }
}
