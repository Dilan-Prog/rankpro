<?php

namespace App\Http\Controllers;

use App\Models\Articulo;
use App\Support\CasosExito;
use App\Support\CiudadesSeo;
use App\Support\Clusters;
use App\Support\LandingsAds;
use App\Support\LandingsAutomatizacion;
use App\Support\LandingsSocial;
use App\Support\LandingsWeb;
use App\Support\Servicios;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * Páginas públicas del sitio de RankPro.
 *
 * Toda la arquitectura de contenido (servicios, nosotros, contacto y legales)
 * se sirve desde aquí. Los datos de cada servicio viven en servicios() para que
 * el hub (/servicios) y el detalle (/servicios/{slug}) usen exactamente la misma
 * fuente de verdad y no se desincronicen los textos ni los iconos.
 */
class PaginasController extends Controller
{
    /**
     * Catálogo de servicios. La clave del array es el slug de la URL.
     *
     * La fuente de verdad vive en App\Support\Servicios para que el navbar y el
     * footer usen exactamente los mismos nombres, iconos y resumenes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function servicios(): array
    {
        return Servicios::todos();
    }

    public function nosotros(): View
    {
        return view('pages.nosotros', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function serviciosIndex(): View
    {
        return view('pages.servicios.index', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function serviciosShow(string $slug): View
    {
        $servicios = $this->servicios();

        abort_unless(array_key_exists($slug, $servicios), 404);

        // Algunos servicios tienen una landing propia orientada a conversion
        // (pages/servicios/{slug}.blade.php). El resto usa la plantilla generica.
        $vista = view()->exists("pages.servicios.{$slug}")
            ? "pages.servicios.{$slug}"
            : 'pages.servicios.show';

        return view($vista, [
            'servicio' => $servicios[$slug],
            'otrosServicios' => array_values(array_diff_key($servicios, [$slug => true])),
            'articulos' => $this->articulosDelServicio($slug),
            'guias' => $this->guiasDelServicio($servicios[$slug]),
        ]);
    }

    public function seoCiudad(string $slugCiudad): View
    {
        $ciudad = CiudadesSeo::encontrar($slugCiudad);

        abort_if($ciudad === null, 404);

        $servicios = $this->servicios();
        $servicio = $servicios[CiudadesSeo::SERVICIO];

        return view('pages.servicios.seo-ciudad', [
            'ciudad' => $ciudad,
            'servicio' => $servicio,
            'otrasCiudades' => CiudadesSeo::enlaces($slugCiudad),
            'otrosServicios' => array_map(
                static fn (string $slug): array => $servicios[$slug],
                ['desarrollo-web', 'sem-google-ads', 'pagespeed-core-web-vitals'],
            ),
            'guias' => $this->guiasDelServicio($servicio),
        ]);
    }

    public function webLanding(string $slugLanding): View
    {
        $landing = LandingsWeb::encontrar($slugLanding);

        abort_if($landing === null, 404);

        $servicios = $this->servicios();
        $servicio = $servicios[LandingsWeb::SERVICIO];

        return view('pages.servicios.web-landing', [
            'landing' => $landing,
            'servicio' => $servicio,
            'otrasLandings' => LandingsWeb::enlaces($slugLanding),
            // Las de desarrollo mas las de SEO: la landing enlaza a la guia de SEO local.
            'guias' => $this->guiasDelServicio($servicio) + $this->guiasDelServicio($servicios[CiudadesSeo::SERVICIO]),
        ]);
    }

    public function automatizacionLanding(string $slugLanding): View
    {
        $landing = LandingsAutomatizacion::encontrar($slugLanding);

        abort_if($landing === null, 404);

        $servicio = $this->servicios()[LandingsAutomatizacion::SERVICIO];

        return view('pages.servicios.automatizacion-landing', [
            'landing' => $landing,
            'servicio' => $servicio,
            'guias' => $this->guiasDelServicio($servicio),
        ]);
    }

    public function adsLanding(string $slugLanding): View
    {
        $landing = LandingsAds::encontrar($slugLanding);

        abort_if($landing === null, 404);

        return view("pages.servicios.ads-{$slugLanding}", [
            'landing' => $landing,
            'servicio' => $this->servicios()[LandingsAds::SERVICIO],
            'guias' => $this->guiasDelServicio($landing),
        ]);
    }

    public function socialLanding(string $slugLanding): View
    {
        $landing = LandingsSocial::encontrar($slugLanding);

        abort_if($landing === null, 404);

        $servicio = $this->servicios()[LandingsSocial::SERVICIO];

        return view('pages.servicios.social-landing', [
            'landing' => $landing,
            'servicio' => $servicio,
            'afines' => LandingsSocial::afines($slugLanding),
            'guias' => $this->guiasDelServicio($servicio),
        ]);
    }

    /**
     * Articulos del blog que apoyan a un servicio (spokes -> hub).
     *
     * Hay dos caminos y los dos cuentan, porque cubren casos distintos:
     *
     *  1. Relacion explicita en la tabla pivote articulo_servicio: sirve para el
     *     articulo que habla de un servicio aunque viva en otro cluster (por
     *     ejemplo una guia de analitica que apoya a Google Ads).
     *  2. Pertenencia al cluster tematico que apoya al servicio: es la relacion
     *     estructural por defecto, sin tener que etiquetar articulo por articulo.
     *
     * Se unen en una sola consulta con OR para no traer dos colecciones y tener
     * que deduplicar en PHP: asi el "3 mas recientes" se aplica sobre el conjunto
     * real y no sobre uno de los dos caminos.
     *
     * @return Collection<int, Articulo>
     */
    private function articulosDelServicio(string $slug): Collection
    {
        $clusters = Clusters::deServicio($slug);

        return Articulo::query()
            ->publicados()
            ->where(function ($query) use ($slug, $clusters) {
                $query->delServicio($slug);

                if ($clusters !== []) {
                    $query->orWhereIn('cluster', $clusters);
                }
            })
            ->limit(3)
            ->get();
    }

    /**
     * Guías fijas que declara el servicio y que ya están publicadas, en el
     * orden del catálogo. Las no publicadas se descartan para no enlazar a 404.
     *
     * @param  array<string, mixed>  $servicio
     * @return array<string, string> slug => texto del enlace
     */
    private function guiasDelServicio(array $servicio): array
    {
        $guias = $servicio['guias'] ?? [];

        if ($guias === []) {
            return [];
        }

        $publicadas = Articulo::query()
            ->publicados()
            ->whereIn('slug', array_keys($guias))
            ->pluck('slug')
            ->all();

        return array_intersect_key($guias, array_flip($publicadas));
    }

    /**
     * Listado de casos de exito. Los conteos por sector y servicio viajan
     * precalculados para que los filtros del listado digan la verdad ("0")
     * sin recorrer los casos otra vez en la vista.
     */
    public function casosIndex(): View
    {
        return view('pages.casos.index', [
            'casos' => CasosExito::todos(),
            'conteos' => CasosExito::conteos(),
        ]);
    }

    public function casosShow(string $slug): View
    {
        $caso = CasosExito::porSlug($slug);

        // Mismo criterio que serviciosShow: un slug desconocido es un 404 real,
        // nunca una pagina vacia con estado 200 (soft 404).
        abort_if($caso === null, 404);

        return view('pages.casos.show', [
            'caso' => $caso,
            'otros' => CasosExito::otros($slug),
        ]);
    }

    public function contacto(): View
    {
        return view('pages.contacto', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function terminos(): View
    {
        return view('pages.legal.terminos');
    }

    public function privacidad(): View
    {
        return view('pages.legal.privacidad');
    }

    public function cookies(): View
    {
        return view('pages.legal.cookies');
    }
}
