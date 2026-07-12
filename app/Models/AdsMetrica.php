<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsMetrica extends Model
{
    use HasFactory;

    protected $table = 'ads_metricas';

    protected $fillable = [
        'ads_campana_id',
        'cliente_id',
        'mes',
        'anio',
        'inversion_real',
        'impresiones',
        'clics',
        'ctr',
        'cpc',
        'conversiones',
        'cpl',
        'cpa',
        'roas',
        'valor_conversion',
    ];

    protected $casts = [
        'inversion_real' => 'decimal:2',
        'ctr' => 'decimal:3',
        'cpc' => 'decimal:2',
        'cpl' => 'decimal:2',
        'cpa' => 'decimal:2',
        'roas' => 'decimal:2',
        'valor_conversion' => 'decimal:2',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class, 'ads_campana_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
