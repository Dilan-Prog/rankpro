<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila única (id=1) con la llave JSON de la cuenta de servicio de Google. La llave va cifrada
 * (cast 'encrypted', usa APP_KEY) y NUNCA sale de aquí hacia vistas, API ni logs: lo único que se
 * muestra es `client_email` y si hay llave guardada.
 */
class GscConexion extends Model
{
    public const SIN_LLAVE = 'sin_llave';

    public const PENDIENTE = 'pendiente';

    public const OK = 'ok';

    public const ERROR = 'error';

    protected $table = 'gsc_conexion';

    protected $fillable = [
        'client_email', 'llave_json', 'estado', 'ultima_prueba_en', 'ultima_prueba_ok',
        'ultima_prueba_resultado', 'propiedades_detectadas', 'actualizado_por',
    ];

    // Oculta por si alguien serializa el modelo (toArray/JSON): la llave no debe viajar nunca.
    protected $hidden = ['llave_json'];

    protected $casts = [
        'llave_json' => 'encrypted',
        'ultima_prueba_en' => 'datetime',
        'ultima_prueba_ok' => 'boolean',
        'propiedades_detectadas' => 'array',
    ];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public static function actual(): ?self
    {
        return static::query()->find(1);
    }

    public function tieneLlave(): bool
    {
        return filled($this->getRawOriginal('llave_json'));
    }

    /**
     * Credenciales para firmar el JWT; solo las usa el cliente HTTP.
     *
     * @return array{client_email: string, private_key: string}|null
     */
    public function credenciales(): ?array
    {
        if (! $this->tieneLlave()) {
            return null;
        }

        try {
            $json = json_decode((string) $this->llave_json, true);
        } catch (\Throwable) {
            // APP_KEY cambió: el cifrado ya no se puede leer; se trata como "sin llave utilizable".
            return null;
        }

        if (! is_array($json) || ! isset($json['client_email'], $json['private_key'])) {
            return null;
        }

        return ['client_email' => $json['client_email'], 'private_key' => $json['private_key']];
    }
}
