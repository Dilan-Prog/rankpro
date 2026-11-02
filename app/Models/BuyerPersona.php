<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Buyer persona capturada a mano para un cliente; `orden` define el orden de las tarjetas. */
class BuyerPersona extends Model
{
    protected $table = 'buyer_personas';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'perfil',
        'dolores',
        'mensaje',
        'canales',
        'orden',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Los dolores se capturan uno por línea; la vista los muestra como lista. */
    public function doloresLista(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->dolores))));
    }
}
