<?php

namespace App\Http\Controllers;

use App\Support\Servicios;
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

        foreach ($this->paginas() as $path => $meta) {
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
     * Paginas estaticas mas una entrada por cada servicio del catalogo.
     *
     * Las URLs de servicio se derivan de App\Support\Servicios para que dar de
     * alta un servicio nuevo no obligue a tocar tambien este archivo.
     *
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    private function paginas(): array
    {
        $paginas = [];

        foreach (self::PAGES as $path => $meta) {
            $paginas[$path] = $meta;

            // Los detalles de servicio van justo despues del hub.
            if ($path === '/servicios') {
                foreach (Servicios::todos() as $servicio) {
                    $slug = $servicio['slug'];
                    $paginas["/servicios/{$slug}"] = ['monthly', '0.8', "pages.servicios.{$slug}"];
                }
            }
        }

        return $paginas;
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
