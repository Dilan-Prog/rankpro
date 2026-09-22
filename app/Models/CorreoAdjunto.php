<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un archivo adjunto a un envío de correo: guarda su propia copia en disco
 * (no referencia in-place a Archivo, ver CorreoAdjuntosController::desdeArchivo())
 * para no romperse si el archivo original del módulo Archivos se borra después.
 */
class CorreoAdjunto extends Model
{
    use HasFactory;

    protected $table = 'correo_adjuntos';

    protected $fillable = [
        'envio_id', 'nombre', 'ruta', 'disco', 'mime', 'tamano', 'subido_por',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(CorreoEnvio::class, 'envio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
