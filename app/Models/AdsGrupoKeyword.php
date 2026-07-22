<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsGrupoKeyword extends Model
{
    use HasFactory;

    protected $table = 'ads_grupo_keywords';

    protected $fillable = [
        'ads_grupo_id',
        'keyword',
        'volumen_busqueda',
        'competencia',
        'cpc',
        'datos_personalizados',
    ];

    protected $casts = [
        'volumen_busqueda' => 'integer',
        'cpc' => 'decimal:2',
        'datos_personalizados' => 'array',
    ];

    public function adsGrupo(): BelongsTo
    {
        return $this->belongsTo(AdsGrupo::class);
    }
}
