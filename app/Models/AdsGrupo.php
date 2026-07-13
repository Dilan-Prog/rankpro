<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsGrupo extends Model
{
    use HasFactory;

    protected $table = 'ads_grupos';

    protected $fillable = [
        'ads_campana_id',
        'nombre',
        'audiencia',
        'presupuesto',
        'keywords',
        'estado',
    ];

    protected $casts = [
        'presupuesto' => 'decimal:2',
        'keywords' => 'array',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }
}
