<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable append-only log entry for a Servicio's lifecycle events (see Servicio::eventos()). */
class ServicioEvento extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'servicio_id',
        'usuario_id',
        'descripcion',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
