<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Archivo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'nombre',
        'tipo',
        'ruta_archivo',
        'tamano',
        'extension',
        'subido_por',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
