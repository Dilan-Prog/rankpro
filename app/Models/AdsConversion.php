<?php

namespace App\Models;

use App\Enums\EstadoConversion;
use App\Enums\TipoConversion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsConversion extends Model
{
    use HasFactory;

    protected $table = 'ads_conversiones';

    protected $fillable = [
        'cliente_id',
        'ads_clic_id',
        'visitor_id',
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
    ];

    protected $casts = [
        'tipo' => TipoConversion::class,
        'valor' => 'decimal:2',
        'estado' => EstadoConversion::class,
        'exportada_at' => 'datetime',
        'metadata' => 'array',
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
}
