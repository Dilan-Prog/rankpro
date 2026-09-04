<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una medición de posición de una keyword en una fecha, con la nota de qué se
 * hizo ese mes para moverla. Ver la migración para por qué la ronda no tiene
 * tabla propia.
 */
class KeywordMedicion extends Model
{
    use HasFactory;

    protected $table = 'keyword_mediciones';

    protected $fillable = [
        'keyword_id',
        'lista_id',
        'fecha',
        'posicion',
        'url',
        'nota',
        'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'posicion' => 'integer',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(KeywordLista::class, 'lista_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'keyword_id' => $this->keyword_id,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'posicion' => $this->posicion,
            'url' => $this->url,
            'nota' => $this->nota,
            'registrado_por' => $this->usuario?->name,
        ];
    }
}
