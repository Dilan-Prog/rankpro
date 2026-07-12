<?php

namespace App\Models;

use App\Enums\EstadoKeyword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'campana_id',
        'keyword',
        'tipo',
        'volumen_busqueda',
        'dificultad',
        'cpc_estimado',
        'intencion',
        'idioma',
        'pais',
        'herramienta_origen',
        'url_asignada',
        'posicion_actual',
        'estado',
        'fecha_incorporacion',
        'notas',
    ];

    protected $casts = [
        'estado' => EstadoKeyword::class,
        'cpc_estimado' => 'decimal:2',
        'fecha_incorporacion' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class, 'campana_id');
    }
}
