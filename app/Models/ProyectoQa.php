<?php

namespace App\Models;

use App\Enums\ResultadoQa;
use App\Enums\TipoPruebaQa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoQa extends Model
{
    use HasFactory;

    protected $table = 'proyecto_qa';

    protected $fillable = [
        'proyecto_id',
        'tipo_prueba',
        'resultado',
        'notas',
    ];

    protected $casts = [
        'tipo_prueba' => TipoPruebaQa::class,
        'resultado' => ResultadoQa::class,
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
