<?php

namespace App\Models;

use App\Enums\EstadoAdsCreativo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCreativo extends Model
{
    use HasFactory;

    protected $table = 'ads_creativos';

    protected $fillable = [
        'ads_campana_id',
        'titulo',
        'copy',
        'tipo',
        'url_creativo',
        'ctr',
        'estado',
        'ab_testing',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoAdsCreativo::class,
        'ctr' => 'decimal:3',
        'ab_testing' => 'boolean',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class, 'ads_campana_id');
    }
}
