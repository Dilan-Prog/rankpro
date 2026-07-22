<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdsEmbudoEtapa extends Model
{
    use HasFactory;

    protected $table = 'ads_embudo_etapas';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function conversiones(): HasMany
    {
        return $this->hasMany(AdsConversion::class, 'ads_embudo_etapa_id');
    }
}
