<?php

namespace App\Support;

/**
 * Qué medición carga el sitio público en esta petición.
 *
 * Hay dos maneras de cargar las etiquetas de Google y nunca deben convivir:
 *  - Con GTM_ID: un contenedor de Google Tag Manager despliega GA4 y Google Ads
 *    (modo recomendado: un solo punto de control).
 *  - Sin GTM_ID pero con GOOGLE_ADS_ID: se carga gtag.js de Google Ads directo.
 *
 * En los dos casos solo se emite en los entornos de config('analytics.entornos'),
 * para que una copia del .env en staging no mande datos a las propiedades reales.
 */
class Medicion
{
    /** Formato de un ID de Google Ads; cualquier otra cosa se ignora en vez de imprimirse en el HTML. */
    private const FORMATO_ADS = '/^AW-\d{6,15}$/';

    private static function entornoActivo(): bool
    {
        return in_array(config('app.env'), config('analytics.entornos'), true);
    }

    public static function gtmActivo(): bool
    {
        return (bool) config('analytics.gtm_id') && self::entornoActivo();
    }

    /** ID de Google Ads válido, o null si no está configurado o tiene un formato inesperado. */
    public static function adsId(): ?string
    {
        $id = trim((string) config('analytics.google_ads_id'));

        return preg_match(self::FORMATO_ADS, $id) === 1 ? $id : null;
    }

    /** gtag.js de Google Ads directo: solo si no hay GTM (si lo hay, la etiqueta se despliega desde GTM). */
    public static function adsDirectoActivo(): bool
    {
        return self::adsId() !== null && self::entornoActivo() && ! self::gtmActivo();
    }

    /** ¿Se carga algo que requiera el consentimiento de la persona? Decide si se muestra el banner de cookies. */
    public static function hayMedicion(): bool
    {
        return self::gtmActivo() || self::adsDirectoActivo();
    }
}
