<?php

namespace App\Models;

use App\Enums\EstadoOnPageAccion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoOnPageAccion extends Model
{
    use HasFactory;

    protected $table = 'seo_onpage_acciones';

    protected $fillable = [
        'seo_campana_id',
        'url_pagina',
        'accion',
        'fecha',
        'responsable_id',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'estado' => EstadoOnPageAccion::class,
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
