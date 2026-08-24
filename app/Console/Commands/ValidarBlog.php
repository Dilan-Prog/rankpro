<?php

namespace App\Console\Commands;

use App\Enums\EstadoArticulo;
use App\Models\Articulo;
use App\Support\Clusters;
use App\Support\Servicios;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Validacion editorial del blog antes de publicar.
 *
 * Existe para que 30 articulos no salgan a produccion con metadatos rotos.
 * Un meta_description de 40 caracteres, un cluster que ya no existe o un
 * enlace interno a una URL borrada no rompen la aplicacion: rompen el
 * posicionamiento, y eso no lo detecta ningun test de PHPUnit.
 *
 * Devuelve exit code 1 si hay ERRORES (o AVISOS con --estricto), para poder
 * encadenarlo en CI antes de un despliegue.
 */
class ValidarBlog extends Command
{
    protected $signature = 'blog:validar
                            {--estricto : Los avisos tambien hacen fallar el comando}
                            {--slug= : Validar unicamente el articulo con este slug}
                            {--publicados : Validar solo los articulos en estado publicado}';

    protected $description = 'Valida metadatos, enlaces internos y coherencia editorial de los articulos del blog';

    private const SEVERIDAD_ERROR = 'error';

    private const SEVERIDAD_AVISO = 'aviso';

    /** @var list<array{severidad: string, slug: string, campo: string, detalle: string}> */
    private array $hallazgos = [];

    /** @var array<string, true>|null Cache de slugs existentes (incluye borrados logicos). */
    private ?array $slugsExistentes = null;

    public function handle(): int
    {
        $articulos = $this->articulos();

        if ($articulos->isEmpty()) {
            $this->warn('No hay articulos que validar con los filtros indicados.');

            return self::SUCCESS;
        }

        $this->info("Validando {$articulos->count()} articulo(s)...");
        $this->newLine();

        $duplicados = $this->slugsDuplicados();
        $serviciosValidos = array_keys(Servicios::todos());
        $clustersValidos = Clusters::slugs();

        foreach ($articulos as $articulo) {
            $this->validarSlug($articulo, $duplicados);
            $this->validarClasificacion($articulo, $clustersValidos);
            $this->validarAutor($articulo);
            $this->validarServicios($articulo, $serviciosValidos);
            $this->validarRelacionados($articulo);
            $this->validarMetadatos($articulo);
            $this->validarFechas($articulo);
            $this->validarContenido($articulo);
            $this->validarEnlacesInternos($articulo);
            $this->validarEditorial($articulo);
        }

        return $this->reportar();
    }

    // ------------------------------------------------------------------
    // Seleccion
    // ------------------------------------------------------------------

    /** @return \Illuminate\Database\Eloquent\Collection<int, Articulo> */
    private function articulos()
    {
        $query = Articulo::query()->with(['autor', 'relacionServicios', 'relacionados']);

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        if ($this->option('publicados')) {
            $query->where('estado', EstadoArticulo::Publicado);
        }

        return $query->orderBy('slug')->get();
    }

    /**
     * Slugs repetidos contando los borrados logicos.
     *
     * La columna es unique en base de datos, pero el unique no ve las filas con
     * deleted_at: restaurar un articulo borrado puede chocar con uno vivo.
     *
     * @return list<string>
     */
    private function slugsDuplicados(): array
    {
        return DB::table('articulos')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug')
            ->all();
    }

    // ------------------------------------------------------------------
    // Comprobaciones
    // ------------------------------------------------------------------

    /** @param list<string> $duplicados */
    private function validarSlug(Articulo $articulo, array $duplicados): void
    {
        $slug = (string) $articulo->slug;

        if (trim($slug) === '') {
            $this->error_('(sin slug)', 'slug', 'El slug esta vacio: el articulo no tiene URL.');

            return;
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->error_($slug, 'slug', 'Solo se admite [a-z0-9-] sin mayusculas, acentos ni guiones sueltos.');
        }

        if (in_array($slug, $duplicados, true)) {
            $this->error_($slug, 'slug', 'Slug duplicado contando los borrados logicos: restaurar colisionaria.');
        }
    }

    /** @param list<string> $clustersValidos */
    private function validarClasificacion(Articulo $articulo, array $clustersValidos): void
    {
        if (! in_array((string) $articulo->cluster, $clustersValidos, true)) {
            $this->error_($articulo->slug, 'cluster', "El cluster '{$articulo->cluster}' no existe en Clusters::slugs().");
        }
    }

    private function validarAutor(Articulo $articulo): void
    {
        if (! $articulo->autor) {
            $this->error_($articulo->slug, 'autor_id', "autor_id={$articulo->autor_id} no apunta a ningun usuario existente.");
        }
    }

    /** @param list<string> $serviciosValidos */
    private function validarServicios(Articulo $articulo, array $serviciosValidos): void
    {
        foreach ($articulo->relacionServicios as $relacion) {
            if (! in_array($relacion->servicio_slug, $serviciosValidos, true)) {
                $this->error_(
                    $articulo->slug,
                    'servicio_slug',
                    "'{$relacion->servicio_slug}' no existe en Servicios::todos()."
                );
            }
        }
    }

    private function validarRelacionados(Articulo $articulo): void
    {
        // Se consulta la pivote a pelo: la relacion Eloquent aplica el
        // SoftDeletes del otro extremo y esconde justo lo que buscamos.
        $ids = DB::table('articulo_relacionado')
            ->where('articulo_id', $articulo->id)
            ->pluck('relacionado_id');

        foreach ($ids as $id) {
            $destino = Articulo::withTrashed()->find($id);

            if (! $destino) {
                $this->error_($articulo->slug, 'relacionados', "El relacionado id={$id} no existe.");

                continue;
            }

            if ($destino->trashed()) {
                $this->error_($articulo->slug, 'relacionados', "El relacionado '{$destino->slug}' esta borrado.");
            }
        }
    }

    private function validarMetadatos(Articulo $articulo): void
    {
        $metaTitle = trim((string) $articulo->meta_title);
        $metaDescription = trim((string) $articulo->meta_description);

        if ($metaTitle === '') {
            $this->error_($articulo->slug, 'meta_title', 'Vacio.');
        } elseif (mb_strlen($metaTitle) > 60) {
            $this->error_($articulo->slug, 'meta_title', mb_strlen($metaTitle).' caracteres: el maximo util en la SERP es 60.');
        }

        if ($metaDescription === '') {
            $this->error_($articulo->slug, 'meta_description', 'Vacia.');
        } else {
            $largo = mb_strlen($metaDescription);

            if ($largo < 70 || $largo > 160) {
                $this->error_($articulo->slug, 'meta_description', "{$largo} caracteres: el rango util es 70-160.");
            }
        }
    }

    private function validarFechas(Articulo $articulo): void
    {
        if ($articulo->estado === EstadoArticulo::Publicado && ! $articulo->fecha_publicacion) {
            $this->error_($articulo->slug, 'fecha_publicacion', 'Estado publicado sin fecha de publicacion.');
        }

        if ($articulo->fecha_publicacion
            && $articulo->fecha_actualizacion
            && $articulo->fecha_actualizacion->lt($articulo->fecha_publicacion)) {
            $this->error_(
                $articulo->slug,
                'fecha_actualizacion',
                'Anterior a fecha_publicacion: el dateModified del schema quedaria antes del datePublished.'
            );
        }
    }

    private function validarContenido(Articulo $articulo): void
    {
        if (trim((string) $articulo->contenido) !== '' && trim((string) $articulo->contenido_html) === '') {
            $this->error_(
                $articulo->slug,
                'contenido_html',
                'Hay Markdown pero no HTML: el render no se ejecuto al guardar.'
            );
        }
    }

    /**
     * Enlaces internos rotos.
     *
     * Se resuelven contra el router de Laravel, no con peticiones HTTP: el
     * comando tiene que poder correr en CI sin servidor levantado. Ademas de
     * que la ruta exista, se comprueba el parametro cuando la ruta es de
     * catch-all ({slug} acepta cualquier cosa, asi que "existe la ruta" no
     * significa "existe la pagina").
     */
    private function validarEnlacesInternos(Articulo $articulo): void
    {
        $html = (string) $articulo->contenido_html;

        if (trim($html) === '') {
            return;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $coincidencias);

        foreach (array_unique($coincidencias[1] ?? []) as $href) {
            $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
            $ruta = $this->rutaInterna($href, $host);

            if ($ruta === null) {
                continue; // Externo, ancla, mailto o tel: no es asunto nuestro.
            }

            if (! $this->resuelve($ruta)) {
                $this->error_($articulo->slug, 'enlace interno', "URL rota: {$href}");
            }
        }
    }

    private function validarEditorial(Articulo $articulo): void
    {
        $palabras = (int) $articulo->palabras;

        if ($palabras < 1500 || $palabras > 2500) {
            $this->aviso($articulo->slug, 'palabras', "{$palabras} palabras: el estandar editorial es 1500-2500.");
        }

        $h2 = count(array_filter((array) $articulo->toc, static fn ($e) => ($e['nivel'] ?? 0) === 2));

        if ($h2 < 1) {
            $this->aviso($articulo->slug, 'toc', 'Ningun H2: sin estructura no hay tabla de contenidos ni saltos en la SERP.');
        }

        if (trim((string) $articulo->imagen_destacada) === '') {
            $this->aviso($articulo->slug, 'imagen_destacada', 'Sin imagen destacada.');
        } elseif (trim((string) $articulo->imagen_alt) === '') {
            $this->aviso($articulo->slug, 'imagen_alt', 'Imagen destacada sin texto alternativo.');
        }

        if ($articulo->relacionServicios->isEmpty()) {
            $this->aviso(
                $articulo->slug,
                'servicios',
                'No enlaza con ninguna pagina de servicio: spoke sin hub.'
            );
        }

        if ($articulo->relacionados->isEmpty()) {
            $companeros = Articulo::query()
                ->where('cluster', $articulo->cluster)
                ->where('id', '!=', $articulo->id)
                ->count();

            if ($companeros === 0) {
                $this->aviso($articulo->slug, 'relacionados', 'Sin relacionados y unico articulo de su cluster.');
            }
        }

        if (trim((string) $articulo->resumen) === '') {
            $this->aviso($articulo->slug, 'resumen', 'Vacio: es el texto de las tarjetas del indice.');
        }
    }

    // ------------------------------------------------------------------
    // Resolucion de enlaces
    // ------------------------------------------------------------------

    /** Devuelve la ruta relativa si el href apunta al propio sitio, o null. */
    private function rutaInterna(string $href, ?string $host): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            return null; // Protocol-relative: siempre externo en la practica.
        }

        if (str_starts_with($href, '/')) {
            return $this->soloRuta($href);
        }

        if (preg_match('#^https?://#i', $href)) {
            $hrefHost = parse_url($href, PHP_URL_HOST);

            if ($host && $hrefHost && strcasecmp($hrefHost, $host) === 0) {
                return $this->soloRuta(parse_url($href, PHP_URL_PATH) ?: '/');
            }
        }

        return null;
    }

    private function soloRuta(string $url): string
    {
        $ruta = strtok($url, '?#');

        return $ruta === false || $ruta === '' ? '/' : $ruta;
    }

    private function resuelve(string $ruta): bool
    {
        try {
            $coincidencia = Route::getRoutes()->match(Request::create($ruta, 'GET'));
        } catch (\Throwable) {
            return false;
        }

        $nombre = $coincidencia->getName();
        $parametros = $coincidencia->parameters();

        // Las rutas con comodin aceptan cualquier slug: hay que comprobar que
        // el recurso exista de verdad, o /blog/lo-que-sea pareceria valido.
        return match ($nombre) {
            'blog.show' => isset($this->slugsExistentes()[$parametros['slug'] ?? '']),
            'blog.cluster' => Clusters::existe((string) ($parametros['cluster'] ?? '')),
            'servicios.show' => array_key_exists((string) ($parametros['slug'] ?? ''), Servicios::todos()),
            default => true,
        };
    }

    /** @return array<string, true> */
    private function slugsExistentes(): array
    {
        return $this->slugsExistentes ??= Articulo::query()->pluck('slug')->flip()->map(fn () => true)->all();
    }

    // ------------------------------------------------------------------
    // Salida
    // ------------------------------------------------------------------

    private function error_(string $slug, string $campo, string $detalle): void
    {
        $this->hallazgos[] = compact('slug', 'campo', 'detalle') + ['severidad' => self::SEVERIDAD_ERROR];
    }

    private function aviso(string $slug, string $campo, string $detalle): void
    {
        $this->hallazgos[] = compact('slug', 'campo', 'detalle') + ['severidad' => self::SEVERIDAD_AVISO];
    }

    private function reportar(): int
    {
        $errores = array_values(array_filter($this->hallazgos, fn ($h) => $h['severidad'] === self::SEVERIDAD_ERROR));
        $avisos = array_values(array_filter($this->hallazgos, fn ($h) => $h['severidad'] === self::SEVERIDAD_AVISO));

        if ($errores !== []) {
            $this->line('<fg=red;options=bold>ERRORES ('.count($errores).')</>');
            $this->table(
                ['Articulo', 'Campo', 'Detalle'],
                array_map(fn ($h) => [
                    "<fg=red>{$h['slug']}</>",
                    $h['campo'],
                    $h['detalle'],
                ], $errores)
            );
        }

        if ($avisos !== []) {
            $this->line('<fg=yellow;options=bold>AVISOS ('.count($avisos).')</>');
            $this->table(
                ['Articulo', 'Campo', 'Detalle'],
                array_map(fn ($h) => [
                    "<fg=yellow>{$h['slug']}</>",
                    $h['campo'],
                    $h['detalle'],
                ], $avisos)
            );
        }

        if ($errores === [] && $avisos === []) {
            $this->line('<fg=green;options=bold>Sin hallazgos: el blog esta limpio.</>');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>Resumen:</> <fg=red>%d error(es)</>, <fg=yellow>%d aviso(s)</>.',
            count($errores),
            count($avisos)
        ));

        if ($errores !== []) {
            return self::FAILURE;
        }

        if ($avisos !== [] && $this->option('estricto')) {
            $this->line('<fg=red>--estricto activo: los avisos hacen fallar el comando.</>');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
