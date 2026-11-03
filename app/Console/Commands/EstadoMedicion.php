<?php

namespace App\Console\Commands;

use App\Support\Medicion;
use Illuminate\Console\Command;

/**
 * Explica qué etiquetas de Google carga el sitio público con la configuración ACTUAL del servidor
 * y, si no carga ninguna, cuál condición falla.
 *
 * Existe porque la etiqueta de Google Ads vive en components/analytics/google-ads.blade.php y solo se
 * emite si se cumplen varias condiciones a la vez (entorno, GTM, formato del ID): cuando no aparece en
 * el código fuente, ver el archivo no dice por qué. Se corre en el servidor: `php artisan analytics:estado`.
 */
class EstadoMedicion extends Command
{
    protected $signature = 'analytics:estado';

    protected $description = 'Muestra qué etiquetas de Google (GTM / Google Ads) carga el sitio público y por qué';

    public function handle(): int
    {
        $entorno = (string) config('app.env');
        $entornos = (array) config('analytics.entornos');
        $gtm = (string) config('analytics.gtm_id');
        $adsCrudo = (string) config('analytics.google_ads_id');

        $this->line('Configuración que usa el sitio ahora mismo:');
        $this->table(['Dato', 'Valor'], [
            ['APP_ENV', $entorno !== '' ? $entorno : '(vacío)'],
            ['Entornos que emiten medición', implode(', ', $entornos)],
            ['GTM_ID', $gtm !== '' ? $gtm : '(vacío)'],
            ['GOOGLE_ADS_ID', $adsCrudo !== '' ? $adsCrudo : '(vacío)'],
            ['Configuración en caché', app()->configurationIsCached() ? 'SÍ (config:cache)' : 'no'],
        ]);

        $this->newLine();
        $this->line('Qué se emite en el <head>:');
        $this->line('  GTM (contenedor + Consent Mode): '.(Medicion::gtmActivo() ? 'SÍ' : 'no'));
        $this->line('  Google Ads (gtag.js directo):    '.(Medicion::adsDirectoActivo() ? 'SÍ' : 'no'));
        $this->line('  Banner de cookies:               '.(Medicion::hayMedicion() ? 'SÍ' : 'no'));
        $this->newLine();

        if (Medicion::adsDirectoActivo()) {
            $this->info('La etiqueta '.Medicion::adsId().' SÍ se emite. Si no aparece en el código fuente de la portada, es caché de página (purga la caché en hPanel) o se está viendo otro dominio.');

            return self::SUCCESS;
        }

        $this->warn('La etiqueta de Google Ads NO se emite. Motivo:');

        if (! in_array($entorno, $entornos, true)) {
            $this->line("  - APP_ENV es «{$entorno}» y la medición solo se emite en: ".implode(', ', $entornos).'. Pon APP_ENV=production en el .env del servidor.');
        }

        if ($gtm !== '') {
            $this->line('  - Hay GTM_ID ('.$gtm.'): con GTM la etiqueta de Google Ads debe desplegarse desde el contenedor. Si prefieres la directa, deja GTM_ID vacío.');
        }

        if (Medicion::adsId() === null) {
            $this->line('  - GOOGLE_ADS_ID '.($adsCrudo === '' ? 'está vacío' : "(«{$adsCrudo}») no tiene el formato AW-123456789").'. Debe ser, por ejemplo, AW-18553224619.');
        }

        if (app()->configurationIsCached()) {
            $this->line('  - La configuración está en caché: tras cambiar el .env corre `php artisan config:clear` y luego `php artisan config:cache`.');
        }

        return self::FAILURE;
    }
}
