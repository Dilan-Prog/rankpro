<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada de la bitácora de una actividad del SEO Plan. */
class SeoActividadNota extends Model
{
    protected $table = 'seo_actividad_notas';

    protected $fillable = ['seo_onpage_accion_id', 'user_id', 'nota'];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(SeoOnPageAccion::class, 'seo_onpage_accion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
