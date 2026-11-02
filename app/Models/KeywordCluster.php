<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Agrupación temática de keywords de un cliente (cada clúster apunta a una URL). */
class KeywordCluster extends Model
{
    protected $table = 'keyword_clusters';

    protected $fillable = ['cliente_id', 'nombre', 'url'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'cluster_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(SeoOnPageAccion::class, 'cluster_id');
    }
}
