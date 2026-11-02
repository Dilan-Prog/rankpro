<?php

namespace App\Models;

use App\Enums\FuenteMarca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha de identidad de marca de un cliente (una por cliente).
 * El logo se guarda en el disco privado 'local', igual que Archivos, y se sirve por una ruta autenticada.
 */
class IdentidadMarca extends Model
{
    protected $table = 'identidad_marcas';

    protected $fillable = [
        'cliente_id',
        'logo_ruta',
        'slogan',
        'paleta',
        'fuente_titulos',
        'fuente_texto',
        'voz',
        'tonos',
        'mision',
        'vision',
        'valores',
    ];

    protected $casts = [
        'paleta' => 'array',
        'tonos' => 'array',
        'fuente_titulos' => FuenteMarca::class,
        'fuente_texto' => FuenteMarca::class,
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
