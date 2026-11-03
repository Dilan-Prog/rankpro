<?php

namespace App\Support;

use App\Models\ConfiguracionMedicion;

/**
 * Qué medición carga el sitio público en esta petición.
 *
 * Hay dos fuentes de configuración y la del panel manda:
 *  - PANEL: existe la fila de configuracion_medicion (Configuración > Medición y Google).
 *    Emite solo si está `activa` y el host de la petición está en `dominios`. Ignora
 *    APP_ENV a propósito: el servidor de producción corre con APP_ENV=local y config en
 *    caché, y justo por eso el .env no servía.
 *  - ENV: no hay fila. Comportamiento histórico (config/analytics.php + guardia de
 *    entornos), para no cambiar nada hasta que el dueño guarde su primera configuración.
 *
 * Modo de carga, nunca los dos a la vez (duplicaría cada visita y cada conversión):
 *  - 'gtm':  un contenedor de Tag Manager despliega GA4 y Google Ads.
 *  - 'gtag': sin GTM, un solo gtag.js que configura GA4 y/o Google Ads.
 */
class Medicion
{
    /** Evento del sitio => columna de la etiqueta de conversión. */
    private const CONVERSIONES = [
        'agendar_completado' => 'conversion_agendar',
        'click_whatsapp' => 'conversion_whatsapp',
        'click_telefono' => 'conversion_telefono',
    ];

    public static function config(): ?ConfiguracionMedicion
    {
        return ConfiguracionMedicion::actual();
    }

    /** @return 'panel'|'env' */
    public static function fuente(): string
    {
        return self::config() !== null ? 'panel' : 'env';
    }

    public static function gtmId(): ?string
    {
        $crudo = self::config() ? self::config()->gtm_id : config('analytics.gtm_id');

        return FormatosGoogle::gtm(is_string($crudo) ? $crudo : null);
    }

    public static function ga4Id(): ?string
    {
        // El .env nunca tuvo GA4 directo (se desplegaba desde GTM): solo existe en el panel.
        return FormatosGoogle::ga4(self::config()?->ga4_id);
    }

    /** ID de Google Ads válido, o null si no está configurado o tiene un formato inesperado. */
    public static function adsId(): ?string
    {
        $crudo = self::config() ? self::config()->google_ads_id : config('analytics.google_ads_id');

        return FormatosGoogle::ads(is_string($crudo) ? $crudo : null);
    }

    /** Etiqueta de conversión (AW-x/y) de un evento del sitio, o null si no hay o no es válida. */
    public static function conversion(string $evento): ?string
    {
        $columna = self::CONVERSIONES[$evento] ?? null;

        return $columna ? FormatosGoogle::conversion(self::config()?->{$columna}) : null;
    }

    /** ¿Se permite emitir hoy? (interruptor + host en el panel; entorno en modo .env). */
    public static function activo(?string $host = null): bool
    {
        $config = self::config();

        if ($config === null) {
            return in_array(config('app.env'), (array) config('analytics.entornos'), true);
        }

        return $config->activa && self::hostPermitido($config, $host);
    }

    /** @return 'gtm'|'gtag'|null */
    public static function modo(?string $host = null): ?string
    {
        if (! self::activo($host)) {
            return null;
        }

        if (self::gtmId() !== null) {
            return 'gtm';
        }

        return self::ga4Id() !== null || self::adsId() !== null ? 'gtag' : null;
    }

    public static function gtmActivo(): bool
    {
        return self::modo() === 'gtm';
    }

    public static function gtagActivo(): bool
    {
        return self::modo() === 'gtag';
    }

    /** gtag.js de Google Ads directo (solo sin GTM). Se conserva por compatibilidad. */
    public static function adsDirectoActivo(): bool
    {
        return self::gtagActivo() && self::adsId() !== null;
    }

    /** ¿Se carga algo que requiera el consentimiento de la persona? Decide si se muestra el banner de cookies. */
    public static function hayMedicion(): bool
    {
        return self::modo() !== null;
    }

    /**
     * Foto completa de lo que se emite y por qué lo que no se emite (la usa el panel y analytics:estado).
     *
     * @param  string|null  $host  host a evaluar; por defecto el de la petición actual
     * @return array{fuente: string, activa: bool, modo: ?string, emite: array<string, bool>, motivos: list<string>}
     */
    public static function estado(?string $host = null): array
    {
        $config = self::config();
        $fuente = $config !== null ? 'panel' : 'env';
        $activa = self::activo($host);
        $modo = self::modo($host);

        $gtm = self::gtmId();
        $ga4 = self::ga4Id();
        $ads = self::adsId();

        $emite = [
            'gtm' => $modo === 'gtm',
            'ga4' => $modo === 'gtag' && $ga4 !== null,
            'ads' => $modo === 'gtag' && $ads !== null,
            'banner' => $modo !== null,
        ];

        $motivos = [];

        if (! $activa) {
            array_push($motivos, ...($fuente === 'panel'
                ? self::motivosPanelInactivo($config, $host)
                : [self::motivoEntorno()]));
        }

        // IDs mal escritos: se avisa en vez de callar, porque un ID inválido simplemente no sale.
        $crudoGtm = $config ? $config->gtm_id : config('analytics.gtm_id');
        if (filled($crudoGtm) && $gtm === null) {
            $motivos[] = $fuente === 'env'
                ? 'GTM_ID («'.$crudoGtm.'») no tiene el formato GTM-ABC1234, así que se ignora.'
                : 'El ID de Tag Manager («'.$crudoGtm.'») no tiene el formato GTM-ABC1234, así que se ignora.';
        }
        if ($config !== null && filled($config->ga4_id) && $ga4 === null) {
            $motivos[] = 'El ID de GA4 («'.$config->ga4_id.'») no tiene el formato G-ABCDE12345, así que se ignora.';
        }
        $crudoAds = $config ? $config->google_ads_id : config('analytics.google_ads_id');
        if (filled($crudoAds) && $ads === null) {
            $motivos[] = ($fuente === 'env' ? 'GOOGLE_ADS_ID' : 'El ID de Google Ads')
                .' («'.$crudoAds.'») no tiene el formato AW-123456789, así que se ignora.';
        }

        if ($gtm !== null && ($ga4 !== null || $ads !== null)) {
            $motivos[] = $fuente === 'env'
                ? 'Hay GTM_ID ('.$gtm.'): la etiqueta de Google Ads se despliega desde el contenedor y la directa no se carga (evita contar todo dos veces).'
                : 'Hay un contenedor de Tag Manager ('.$gtm.'): GA4 y Google Ads deben desplegarse desde él, así que la etiqueta directa no se carga (evita contar todo dos veces).';
        }

        if ($gtm === null && $ga4 === null && $ads === null) {
            $motivos[] = $fuente === 'env'
                ? 'GOOGLE_ADS_ID está vacío o no es válido y no hay GTM_ID: no hay nada que cargar.'
                : 'No hay ningún ID válido (Tag Manager, GA4 ni Google Ads): no hay nada que cargar.';
        }

        return [
            'fuente' => $fuente,
            'activa' => $activa,
            'modo' => $modo,
            'emite' => $emite,
            'motivos' => $motivos,
        ];
    }

    /** Host de la petición sin puerto y en minúsculas. */
    public static function hostActual(): string
    {
        return strtolower((string) request()->getHost());
    }

    /**
     * Dominios permitidos ya normalizados (minúsculas, sin esquema, ruta ni puerto), sin repetir.
     *
     * @return list<string>
     */
    public static function dominiosPermitidos(?string $dominios): array
    {
        $lista = [];

        foreach (preg_split('/[\s,;]+/', (string) $dominios, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entrada) {
            $dominio = strtolower(trim($entrada));
            $dominio = preg_replace('#^[a-z]+://#', '', $dominio);
            $dominio = preg_replace('#[/?\#].*$#', '', $dominio);
            $dominio = preg_replace('/:\d+$/', '', $dominio);

            if ($dominio !== '' && ! in_array($dominio, $lista, true)) {
                $lista[] = $dominio;
            }
        }

        return $lista;
    }

    private static function hostPermitido(ConfiguracionMedicion $config, ?string $host): bool
    {
        $host = self::sinWww($host !== null ? strtolower(trim($host)) : self::hostActual());

        foreach (self::dominiosPermitidos($config->dominios) as $dominio) {
            if (self::sinWww($dominio) === $host) {
                return true;
            }
        }

        return false;
    }

    /** «www.x» y «x» cuentan como el mismo sitio. */
    private static function sinWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function motivoEntorno(): string
    {
        $entornos = (array) config('analytics.entornos');

        return 'APP_ENV es «'.config('app.env').'» y la medición solo se emite en: '.implode(', ', $entornos)
            .'. Pon APP_ENV=production en el .env del servidor, o guarda la configuración desde Configuración > Medición y Google (que ignora APP_ENV).';
    }

    /** @return list<string> */
    private static function motivosPanelInactivo(ConfiguracionMedicion $config, ?string $host): array
    {
        $motivos = [];

        if (! $config->activa) {
            $motivos[] = 'La medición está desactivada en el panel (interruptor «Activar medición»).';
        }

        $permitidos = self::dominiosPermitidos($config->dominios);
        $actual = $host !== null ? strtolower(trim($host)) : self::hostActual();

        if ($permitidos === []) {
            $motivos[] = 'No hay dominios permitidos: sin al menos uno no se emite en ningún sitio.';
        } elseif (! self::hostPermitido($config, $host)) {
            $motivos[] = 'El host actual («'.$actual.'») no está en los dominios permitidos ('.implode(', ', $permitidos).').';
        }

        return $motivos;
    }
}
