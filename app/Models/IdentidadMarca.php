<?php

namespace App\Models;

use App\Enums\FuenteMarca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Borra el archivo del logo del disco privado y vacía el campo. Primero se
     * guarda la fila y luego se borra el archivo: si el guardado fallara no se
     * pierde el logo vigente (mismo orden que usa el guardado de la ficha).
     */
    public function quitarLogo(): void
    {
        $ruta = $this->logo_ruta;
        if (! $ruta) {
            return;
        }

        $this->forceFill(['logo_ruta' => null])->save();
        $this->borrarArchivo($ruta);
    }

    /**
     * Solo borra el archivo (la fila la elimina la cascada de la BD). Lo usa la
     * eliminación definitiva del cliente, donde guardar la fila ya no tiene sentido.
     */
    public function borrarArchivoDelLogo(): void
    {
        if ($this->logo_ruta) {
            $this->borrarArchivo($this->logo_ruta);
        }
    }

    private function borrarArchivo(string $ruta): void
    {
        if (Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }
    }
}
