<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Fila única (id=1) con los códigos de Google (GTM, GA4, Google Ads) que carga el
 * sitio público. Si existe, MANDA sobre el .env (ver App\Support\Medicion).
 */
class ConfiguracionMedicion extends Model
{
    public const CACHE_CLAVE = 'medicion.config';

    /** Marcador para recordar "no hay fila": rememberForever no distingue null de "sin caché". */
    private const SIN_FILA = 'sin-fila';

    protected $table = 'configuracion_medicion';

    protected $fillable = [
        'gtm_id', 'ga4_id', 'google_ads_id',
        'conversion_agendar', 'conversion_whatsapp', 'conversion_telefono',
        'activa', 'dominios', 'actualizado_por',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Se lee en cada petición pública: se cachea y se olvida al cambiar.
        static::saved(fn () => Cache::forget(self::CACHE_CLAVE));
        static::deleted(fn () => Cache::forget(self::CACHE_CLAVE));
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** La única fila que existe (o null si nunca se guardó nada). */
    public static function actual(): ?self
    {
        try {
            $valor = Cache::rememberForever(
                self::CACHE_CLAVE,
                fn () => static::query()->find(1) ?? self::SIN_FILA
            );
        } catch (\Throwable) {
            // Tabla aún sin migrar o caché caída: el sitio público no debe romperse por
            // la medición; no se cachea nada y se usa el modo .env.
            return null;
        }

        return $valor instanceof self ? $valor : null;
    }
}
