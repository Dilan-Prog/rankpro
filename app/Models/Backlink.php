<?php

namespace App\Models;

use App\Enums\EstadoBacklink;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backlink extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_campana_id',
        'cliente_id',
        'url_origen',
        'url_destino',
        'da_dr',
        'tipo',
        'estado',
        'fecha_conseguido',
    ];

    protected $casts = [
        'estado' => EstadoBacklink::class,
        'fecha_conseguido' => 'date',
    ];

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class, 'seo_campana_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
