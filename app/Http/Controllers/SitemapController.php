<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Mapa del sitio publico.
     *
     * Se define con paths relativos (no con nombres de ruta) para que el
     * sitemap no se rompa si cambia el nombre interno de alguna ruta.
     *
     * Estructura: path => [changefreq, priority, vista (opcional, para lastmod)]
     *
     * @var array<string, array{0: string, 1: string, 2: string|null}>
     */
    private const PAGES = [
        '/'                                  => ['weekly',  '1.0', 'pages.index'],
        '/nosotros'                          => ['monthly', '0.7', 'pages.nosotros'],
        '/servicios'                         => ['monthly', '0.9', 'pages.servicios.index'],
        '/servicios/sem-google-ads'          => ['monthly', '0.8', 'pages.servicios.show'],
        '/servicios/seo-organico'            => ['monthly', '0.8', 'pages.servicios.show'],
        '/servicios/desarrollo-web'          => ['monthly', '0.8', 'pages.servicios.show'],
        '/servicios/pagespeed-core-web-vitals' => ['monthly', '0.8', 'pages.servicios.show'],
        '/servicios/analytics-data'          => ['monthly', '0.8', 'pages.servicios.show'],
        '/servicios/social-media'            => ['monthly', '0.8', 'pages.servicios.show'],
        '/contacto'                          => ['monthly', '0.7', 'pages.contacto'],
        '/terminos-y-condiciones'            => ['yearly',  '0.3', 'pages.legal.terminos'],
        '/aviso-de-privacidad'               => ['yearly',  '0.3', 'pages.legal.privacidad'],
        '/politica-de-cookies'               => ['yearly',  '0.3', 'pages.legal.cookies'],
    ];

    /**
     * Devuelve el sitemap XML.
     */
    public function index(): Response
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach (self::PAGES as $path => $meta) {
            [$changefreq, $priority, $view] = $meta;

            $xml[] = '    <url>';
            $xml[] = '        <loc>' . htmlspecialchars(url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';

            $lastmod = $this->lastModified($view);
            if ($lastmod !== null) {
                $xml[] = '        <lastmod>' . $lastmod . '</lastmod>';
            }

            $xml[] = '        <changefreq>' . $changefreq . '</changefreq>';
            $xml[] = '        <priority>' . $priority . '</priority>';
            $xml[] = '    </url>';
        }

        $xml[] = '</urlset>';

        return response(implode(PHP_EOL, $xml) . PHP_EOL, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Fecha de ultima modificacion (W3C) tomada del archivo blade de la vista.
     * Devuelve null si la vista no existe: NO se inventan fechas.
     */
    private function lastModified(?string $view): ?string
    {
        if ($view === null) {
            return null;
        }

        $file = resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');

        if (! is_file($file)) {
            return null;
        }

        $time = @filemtime($file);

        return $time ? date('Y-m-d', $time) : null;
    }
}
