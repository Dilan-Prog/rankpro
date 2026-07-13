<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsOptimizacion extends Model
{
    use HasFactory;

    protected $table = 'ads_optimizaciones';

    protected $fillable = [
        'ads_campana_id',
        'fecha',
        'tipo',
        'descripcion',
        'resultado',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
