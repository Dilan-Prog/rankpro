<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plan SEO de un cliente: solo guarda la meta general en texto (las metas medibles son MetricaCliente). */
class SeoPlan extends Model
{
    protected $table = 'seo_planes';

    protected $fillable = ['cliente_id', 'meta_general'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
