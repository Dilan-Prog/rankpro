<?php

namespace App\Models;

use App\Enums\TipoElementoContenido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Elemento del árbol de «Contenido» de un cliente (carpeta, documento, tabla o
 * archivo). Ver la migración de cm_items para el porqué del árbol único.
 */
class ElementoContenido extends Model
{
    use SoftDeletes;

    protected $table = 'cm_items';

    protected $fillable = [
        'cliente_id',
        'parent_id',
        'tipo',
        'nombre',
        'contenido',
        'archivo_path',
        'notas',
    ];

    protected $casts = [
        'tipo' => TipoElementoContenido::class,
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function esCarpeta(): bool
    {
        return $this->tipo === TipoElementoContenido::Carpeta;
    }
}
