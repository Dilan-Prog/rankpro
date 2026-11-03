<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoPosicion extends Model
{
    use HasFactory;

    protected $table = 'seo_posiciones';

    protected $fillable = [
        'seo_campana_id',
        'cliente_id',
        'keyword',
        'url_pagina',
        'posicion_actual',
        'posicion_anterior',
        'variacion',
        'volumen_busqueda',
        'dificultad_keyword',
        'dispositivo',
        'pais',
        'fecha_registro',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    /**
     * Variación de ranking (positivo = mejoró). NULL si falta alguna de las dos
     * posiciones o es 0 ("sin ranking"): restar con `?? 0` inventaba -5 con una
     * posición nueva y +10 al salir del ranking.
     */
    public static function calcularVariacion(?int $anterior, ?int $actual): ?int
    {
        if (! $anterior || ! $actual) {
            return null;
        }

        return $anterior - $actual;
    }

    public function seoCampana(): BelongsTo
    {
        return $this->belongsTo(SeoCampana::class, 'seo_campana_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
