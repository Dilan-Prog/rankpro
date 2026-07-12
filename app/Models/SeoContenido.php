<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoContenido extends Model
{
    use HasFactory;

    protected $table = 'seo_contenido';

    protected $fillable = [
        'seo_campana_id',
        'titulo',
        'keyword_objetivo',
        'url',
        'trafico_generado',
        'estado',
    ];

    protected $casts = [
        'trafico_generado' => 'integer',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }
}
