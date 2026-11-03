<?php

namespace App\Console\Commands;

use App\Support\Medicion;
use Illuminate\Console\Command;

/**
 * Explica qué etiquetas de Google carga el sitio público con la configuración ACTUAL del servidor
 * y, si no carga alguna, por qué (la misma foto que muestra el panel en Medición y Google).
 *
 * Existe porque la medición se decide con varias condiciones a la vez (fuente panel/.env,
 * interruptor, host, entorno, GTM, formato de los IDs) y, cuando algo no aparece en el código
 * fuente, ver los archivos no dice por qué. Se corre en el servidor: `php artisan analytics:estado`.
 */
class EstadoMedicion extends Command
{
    protected $signature = 'analytics:estado {--host= : Host a evaluar (por defecto, el primer dominio permitido del panel)}';

    protected $description = 'Muestra qué etiquetas de Google (GTM / GA4 / Google Ads) carga el sitio público y por qué';

    public function handle(): int
    {
        $config = Medicion::config();

        // En consola no hay petición real: se evalúa un host concreto. Por defecto el primero
        // permitido, que es el caso que le importa al dueño ("¿se emitiría en mi sitio?").
        $host = $this->option('host') ?: ($config ? (Medicion::dominiosPermitidos($config->dominios)[0] ?? null) : null);

        $estado = Medicion::estado($host);

        $this->line('Configuración que usa el sitio ahora mismo:');
        $filas = [
            ['Fuente', $estado['fuente'] === 'panel' ? 'panel (Configuración > Medición y Google)' : '.env / config/analytics.php (no hay configuración en el panel)'],
            ['APP_ENV', (string) config('app.env') ?: '(vacío)'],
            ['Configuración en caché', app()->configurationIsCached() ? 'SÍ (config:cache)' : 'no'],
        ];

        if ($config !== null) {
            $filas[] = ['Medición activada en el panel', $config->activa ? 'SÍ' : 'no'];
            $filas[] = ['Dominios permitidos', implode(', ', Medicion::dominiosPermitidos($config->dominios)) ?: '(ninguno)'];
            $filas[] = ['Host evaluado', $host ?? Medicion::hostActual()];
        } else {
            $filas[] = ['Entornos que emiten medición', implode(', ', (array) config('analytics.entornos'))];
        }

        $filas[] = ['Tag Manager', Medicion::gtmId() ?? '(vacío o inválido)'];
        $filas[] = ['GA4', Medicion::ga4Id() ?? '(vacío o inválido)'];
        $filas[] = ['Google Ads', Medicion::adsId() ?? '(vacío o inválido)'];

        $this->table(['Dato', 'Valor'], $filas);

        $this->newLine();
        $this->line('Modo de carga: '.($estado['modo'] ?? 'ninguno'));
        $this->line('Qué se emite en el <head>:');
        $this->line('  Tag Manager (contenedor + Consent Mode): '.($estado['emite']['gtm'] ? 'SÍ' : 'no'));
        $this->line('  GA4 (gtag.js directo):                   '.($estado['emite']['ga4'] ? 'SÍ' : 'no'));
        $this->line('  Google Ads (gtag.js directo):            '.($estado['emite']['ads'] ? 'SÍ' : 'no'));
        $this->line('  Banner de cookies:                       '.($estado['emite']['banner'] ? 'SÍ' : 'no'));
        $this->newLine();

        if ($estado['emite']['ads']) {
            $this->info('La etiqueta '.Medicion::adsId().' SÍ se emite. Si no aparece en el código fuente de la portada, es caché de página (purga la caché en hPanel) o se está viendo otro dominio.');
        }

        if ($estado['emite']['gtm']) {
            $this->info('El contenedor '.Medicion::gtmId().' SÍ se emite. Si no aparece en el código fuente de la portada, es caché de página (purga la caché en hPanel) o se está viendo otro dominio.');
        }

        if ($estado['motivos'] !== []) {
            $this->warn($estado['modo'] === null ? 'No se emite nada. Motivo:' : 'Avisos:');
            foreach ($estado['motivos'] as $motivo) {
                $this->line('  - '.$motivo);
            }
        }

        if ($estado['fuente'] === 'env' && app()->configurationIsCached()) {
            $this->line('  - La configuración está en caché: tras cambiar el .env corre `php artisan config:clear` y luego `php artisan config:cache`.');
        }

        return $estado['modo'] !== null ? self::SUCCESS : self::FAILURE;
    }
}
