<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsKeywordColumna extends Model
{
    use HasFactory;

    protected $table = 'ads_keyword_columnas';

    protected $fillable = [
        'ads_grupo_id',
        'nombre',
    ];

    public function adsGrupo(): BelongsTo
    {
        return $this->belongsTo(AdsGrupo::class);
    }
}
