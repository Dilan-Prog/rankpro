<?php

namespace App\Http\Controllers;

use App\Models\Articulo;
use App\Support\Clusters;
use App\Support\Servicios;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    /**
     * Mapa del sitio publico.
     *
     * Se define con paths relativos (no con nombres de ruta) para que el
     * sitemap no se rompa si cambia el nombre interno de alguna ruta.
     *
     * Estructura: path => [changefreq, priority, vista (opcional), lastmod (opcional)]
     *
     * El cuarto elemento es un lastmod explicito en formato W3C. Cuando existe
     * gana sobre el filemtime del blade, porque para el contenido dinamico
     * (blog) la fecha real de actualizacion vive en la base de datos y no en
     * el archivo de plantilla.
     *
     * @var array<string, array{0: string, 1: string, 2: string|null, 3?: string|null}>
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

    public function __construct(private readonly Articulo $articulos)
    {
    }

    /**
     * Devuelve el sitemap XML.
     */
    public function index(): Response
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($this->paginas() as $path => $meta) {
            $changefreq = $meta[0];
            $priority = $meta[1];
            $view = $meta[2] ?? null;
            $lastmodExplicito = $meta[3] ?? null;

            $xml[] = '    <url>';
            // La portada se sirve en "/" y su canonica lleva barra final, pero
            // url('/') devuelve el origen sin ella. Declarar en el sitemap una URL
            // distinta de la canonica ensucia el informe de indexacion.
            $loc = $path === '/' ? rtrim(url('/'), '/') . '/' : url($path);

            $xml[] = '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';

            // El lastmod explicito manda; si no lo hay se cae al blade.
            $lastmod = $lastmodExplicito ?? $this->lastModified($view);
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
     * Paginas estaticas, servicios del catalogo y todo el blog publicado.
     *
     * Las URLs de servicio se derivan de App\Support\Servicios y las del blog de
     * la base de datos, para que dar de alta un servicio o publicar un articulo
     * no obligue a tocar tambien este archivo.
     *
     * @return array<string, array{0: string, 1: string, 2: string|null, 3?: string|null}>
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
                    $paginas["/servicios/{$slug}"] = ['monthly', '0.8', $this->vistaDeServicio($slug)];
                }
            }
        }

        return $paginas + $this->paginasDelBlog();
    }

    /**
     * Vista real que sirve la pagina de un servicio.
     *
     * Solo 4 de los 7 servicios tienen landing propia; el resto se pinta con la
     * plantilla generica pages/servicios/show.blade.php (misma resolucion que
     * PaginasController::serviciosShow). Antes se asumia que siempre existia
     * "pages.servicios.{slug}", asi que para pagespeed-core-web-vitals,
     * analytics-data y social-media el archivo no existia, lastModified()
     * devolvia null y esas tres URLs salian del sitemap SIN <lastmod>.
     */
    private function vistaDeServicio(string $slug): string
    {
        return view()->exists("pages.servicios.{$slug}")
            ? "pages.servicios.{$slug}"
            : 'pages.servicios.show';
    }

    /**
     * Indice del blog, paginas de cluster y articulos publicados.
     *
     * Reglas:
     *  - Solo articulos publicados (el scope ya excluye borrador, archivado y
     *    programado a futuro).
     *  - El lastmod sale de fechaEfectiva(): fecha_actualizacion si existe,
     *    si no la de publicacion.
     *  - Un cluster sin articulos publicados NO entra: seria un listado vacio,
     *    es decir una pagina de baja calidad que solo diluye el rastreo.
     *  - Nunca se emiten URLs paginadas (?page=N): son la misma coleccion
     *    troceada y no aportan nada al indice.
     *
     * @return array<string, array{0: string, 1: string, 2: string|null, 3?: string|null}>
     */
    private function paginasDelBlog(): array
    {
        /** @var Collection<int, Articulo> $articulos */
        $articulos = $this->articulos->newQuery()->publicados()->get();

        $paginas = [];

        // Indice del blog: se actualiza cada vez que se publica o se revisa algo.
        $paginas['/blog'] = ['weekly', '0.8', 'pages.blog.index', $this->fechaMasReciente($articulos)];

        foreach (Clusters::slugs() as $cluster) {
            $delCluster = $articulos->where('cluster', $cluster);

            if ($delCluster->isEmpty()) {
                continue;
            }

            $paginas["/blog/categoria/{$cluster}"] = [
                'weekly', '0.5', null, $this->fechaMasReciente($delCluster),
            ];
        }

        foreach ($articulos as $articulo) {
            $paginas["/blog/{$articulo->slug}"] = [
                'monthly', '0.7', null, $this->comoW3c($articulo->fechaEfectiva()),
            ];
        }

        return $paginas;
    }

    /**
     * Fecha efectiva mas reciente de una coleccion de articulos, en W3C.
     * Null si la coleccion esta vacia: no se inventan fechas.
     *
     * @param  Collection<int, Articulo>  $articulos
     */
    private function fechaMasReciente(Collection $articulos): ?string
    {
        $fechas = $articulos
            ->map(static fn (Articulo $a) => $a->fechaEfectiva())
            ->filter()
            ->sortDesc();

        return $this->comoW3c($fechas->first());
    }

    private function comoW3c(?\DateTimeInterface $fecha): ?string
    {
        return $fecha?->format('Y-m-d');
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
