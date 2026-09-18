<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una apertura o un clic de un destinatario. Solo created_at. */
class CorreoEvento extends Model
{
    protected $table = 'correo_eventos';

    public const UPDATED_AT = null;

    protected $fillable = ['destinatario_id', 'tipo', 'url', 'ip', 'user_agent', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(CorreoDestinatario::class, 'destinatario_id');
    }
}
