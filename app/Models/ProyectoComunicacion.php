<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoComunicacion extends Model
{
    use HasFactory;

    protected $table = 'proyecto_comunicaciones';

    protected $fillable = [
        'proyecto_id',
        'fecha',
        'resumen',
        'aprobaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
