<?php

namespace App\Http\Controllers;

use App\Models\Articulo;
use App\Support\Clusters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Blog publico de RankPro.
 *
 * Arquitectura hub-and-spoke: los articulos (spokes) viven bajo un cluster
 * tematico y cada cluster enlaza a su pagina de servicio (hub), que es la que
 * de verdad convierte. Ese enlace de ida y vuelta es la razon de ser del blog.
 */
class BlogController extends Controller
{
    /** Articulos por pagina. 9 encaja exacto en la rejilla de 3 columnas. */
    private const POR_PAGINA = 9;

    public function index(): View
    {
        $articulos = Articulo::publicados()
            ->with('autor')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $this->abortSiLaPaginaNoExiste($articulos);

        return view('pages.blog.index', [
            'articulos' => $articulos,
            'clusters' => Clusters::navegacion(),
        ]);
    }

    public function cluster(string $cluster): View
    {
        abort_unless(Clusters::existe($cluster), 404);

        $info = Clusters::encontrar($cluster);

        $articulos = Articulo::publicados()
            ->delCluster($cluster)
            ->with('autor')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $this->abortSiLaPaginaNoExiste($articulos);

        return view('pages.blog.cluster', [
            'cluster' => $info,
            'articulos' => $articulos,
            'clusters' => Clusters::navegacion(),
            // Hub al que apoya el cluster: la conversion del cluster. Puede ser
            // null si algun dia se define un cluster sin servicio detras (o con
            // un slug que ya no exista en el catalogo); la vista no lo pinta.
            'servicio' => $this->servicioDelCluster($cluster),
        ]);
    }

    public function show(string $slug): View
    {
        // publicados() ya filtra estado y fecha futura: un borrador o un
        // programado devuelven 404 aunque se conozca la URL.
        $articulo = Articulo::publicados()
            ->with(['autor', 'relacionados'])
            ->where('slug', $slug)
            ->firstOrFail();

        $relacionados = $articulo->relacionados
            ->filter(fn (Articulo $a) => $a->estado === \App\Enums\EstadoArticulo::Publicado
                && $a->fecha_publicacion
                && $a->fecha_publicacion->lte(now()))
            ->values();

        // Sin relaciones declaradas a mano, se derivan del cluster: mantener
        // 30 listas manuales no aporta nada frente a "lo mas reciente del tema".
        if ($relacionados->isEmpty()) {
            $relacionados = Articulo::publicados()
                ->delCluster($articulo->cluster)
                ->whereKeyNot($articulo->getKey())
                ->limit(3)
                ->get();
        }

        // Mismo patron que PaginasController::serviciosShow: permite maquetar a
        // mano articulos concretos sin tocar el controlador.
        $vista = view()->exists("pages.blog.articulos.{$slug}")
            ? "pages.blog.articulos.{$slug}"
            : 'pages.blog.show';

        return view($vista, [
            'articulo' => $articulo,
            'autor' => $articulo->autor,
            'cluster' => $articulo->clusterInfo(),
            'servicios' => $articulo->serviciosRelacionados(),
            'relacionados' => $relacionados,
        ]);
    }

    /**
     * Ficha del servicio (hub) al que apoya un cluster, resuelta contra el
     * catalogo para no duplicar nombre ni resumen.
     *
     * @return array<string, mixed>|null
     */
    private function servicioDelCluster(string $cluster): ?array
    {
        $slug = Clusters::servicioDe($cluster);

        if ($slug === null) {
            return null;
        }

        foreach (\App\Support\Servicios::navegacion() as $servicio) {
            if ($servicio['slug'] === $slug) {
                return $servicio;
            }
        }

        return null;
    }

    /**
     * 404 cuando se pide una pagina que no existe (p. ej. ?page=999 con 4
     * paginas). Laravel devolveria un listado vacio con HTTP 200, que Google
     * indexa como thin content y confunde con una pagina real.
     */
    private function abortSiLaPaginaNoExiste(LengthAwarePaginator $articulos): void
    {
        abort_if($articulos->currentPage() > 1 && $articulos->isEmpty(), 404);
    }
}
