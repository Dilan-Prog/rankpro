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
        'lista_id',
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
        'posicion_anterior',
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

    public function lista(): BelongsTo
    {
        return $this->belongsTo(KeywordLista::class, 'lista_id');
    }

    /** Shared shape for admin.keywords index rows and the store()/update() AJAX responses. */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->cliente?->nombre ?? '—',
            'lista_id' => $this->lista_id,
            'lista_nombre' => $this->lista?->nombre,
            'tipo' => $this->tipo,
            'volumen_busqueda' => $this->volumen_busqueda,
            'dificultad' => $this->dificultad,
            'cpc_estimado' => $this->cpc_estimado !== null ? (float) $this->cpc_estimado : null,
            'intencion' => $this->intencion,
            'url_asignada' => $this->url_asignada,
            'posicion_actual' => $this->posicion_actual,
            'posicion_anterior' => $this->posicion_anterior,
            'estado' => $this->estado->value,
            'herramienta_origen' => $this->herramienta_origen,
            'fecha_incorporacion' => $this->fecha_incorporacion?->format('Y-m-d'),
            'notas' => $this->notas,
        ];
    }
}
