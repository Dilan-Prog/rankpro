<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la tabla pivote articulo_servicio.
 *
 * Es un modelo propio y no un belongsToMany porque el otro extremo no es una
 * tabla: los servicios viven en App\Support\Servicios (array estatico). Aqui
 * solo se guarda el slug.
 */
class ArticuloServicio extends Model
{
    protected $table = 'articulo_servicio';

    protected $fillable = ['articulo_id', 'servicio_slug'];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
