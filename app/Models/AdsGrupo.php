<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdsGrupo extends Model
{
    use HasFactory;

    protected $table = 'ads_grupos';

    protected $fillable = [
        'ads_campana_id',
        'nombre',
        'audiencia',
        'presupuesto',
        'estado',
    ];

    protected $casts = [
        'presupuesto' => 'decimal:2',
    ];

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(AdsGrupoKeyword::class)->orderByDesc('id');
    }

    public function columnasPersonalizadas(): HasMany
    {
        return $this->hasMany(AdsKeywordColumna::class);
    }
}
