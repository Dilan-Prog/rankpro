<?php

namespace App\Console\Commands;

use App\Enums\EstadoArticulo;
use App\Models\Articulo;
use App\Models\ArticuloServicio;
use App\Models\User;
use App\Support\Contenido\RenderizadorMarkdown;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Inverso de blog:exportar: siembra la base de datos desde los .md de git.
 *
 * Con esto un entorno nuevo (local o staging) se llena de contenido real sin
 * copiar un dump de produccion, y un articulo revertido en un PR vuelve a la
 * base con un solo comando.
 *
 * contenido_html, toc y palabras NO vienen del fichero: se recalculan aqui con
 * RenderizadorMarkdown, que es la misma pieza que usa el panel al guardar. Asi
 * el HTML importado no puede divergir del que produce la aplicacion.
 */
class ImportarBlog extends Command
{
    protected $signature = 'blog:importar
                            {--slug= : Importar unicamente este slug}
                            {--dry-run : Mostrar que haria sin tocar la base de datos}';

    protected $description = 'Importa los articulos del blog desde resources/content/blog/*.md';

    private bool $simulacion = false;

    public function handle(RenderizadorMarkdown $renderizador): int
    {
        $this->simulacion = (bool) $this->option('dry-run');

        $directorio = base_path(ExportarBlog::DIRECTORIO);

        if (! File::isDirectory($directorio)) {
            $this->error('No existe '.ExportarBlog::DIRECTORIO.'. Ejecuta antes blog:exportar.');

            return self::FAILURE;
        }

        $ficheros = collect(File::files($directorio))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->when($this->option('slug'), fn ($c, $slug) => $c->filter(
                fn ($f) => $f->getFilenameWithoutExtension() === $slug
            ))
            ->sortBy(fn ($f) => $f->getFilename())
            ->values();

        if ($ficheros->isEmpty()) {
            $this->warn('No hay ficheros .md que importar.');

            return self::SUCCESS;
        }

        if ($this->simulacion) {
            $this->line('<fg=cyan;options=bold>DRY RUN: no se escribe nada en la base de datos.</>');
        }

        // ---------------------------------------------------------------
        // Primera pasada: articulos y servicios.
        // ---------------------------------------------------------------
        $filas = [];
        $pendientesRelacion = [];
        $creados = 0;
        $actualizados = 0;
        $saltados = 0;

        foreach ($ficheros as $fichero) {
            $documento = $this->parsear(File::get($fichero->getPathname()));
            $meta = $documento['meta'];
            $slug = (string) ($meta['slug'] ?? $fichero->getFilenameWithoutExtension());

            $autor = $this->resolverAutor($meta['autor'] ?? null);

            if (! $autor) {
                $this->warn("  saltado {$slug}: no existe el usuario '".($meta['autor'] ?? 'sin autor')."'. No se crean usuarios.");
                $saltados++;

                continue;
            }

            $render = $renderizador->procesar($documento['cuerpo']);
            $existia = Articulo::withTrashed()->where('slug', $slug)->exists();

            $atributos = [
                'titulo' => $meta['titulo'] ?? $slug,
                'meta_title' => $meta['meta_title'] ?? '',
                'meta_description' => $meta['meta_description'] ?? '',
                'resumen' => $meta['resumen'] ?? '',
                'contenido' => $documento['cuerpo'],
                'contenido_html' => $render['html'],
                'toc' => $render['toc'],
                'palabras' => $render['palabras'],
                'cluster' => $meta['cluster'] ?? null,
                'estado' => EstadoArticulo::tryFrom((string) ($meta['estado'] ?? '')) ?? EstadoArticulo::Borrador,
                'autor_id' => $autor->id,
                'fecha_publicacion' => $meta['fecha_publicacion'] ?? null,
                'fecha_actualizacion' => $meta['fecha_actualizacion'] ?? null,
                'imagen_destacada' => $meta['imagen_destacada'] ?? null,
                'imagen_alt' => $meta['imagen_alt'] ?? null,
                'og_image' => $meta['og_image'] ?? null,
            ];

            $servicios = array_values((array) ($meta['servicios'] ?? []));
            $relacionados = array_values((array) ($meta['relacionados'] ?? []));

            $filas[] = [
                'slug' => $slug,
                'existia' => $existia,
                'servicios' => $servicios,
            ];

            if ($relacionados !== []) {
                $pendientesRelacion[$slug] = $relacionados;
            }

            $existia ? $actualizados++ : $creados++;

            $this->line(sprintf(
                '  <fg=%s>%s</> %s  (%d palabras, %d servicio(s), %d relacionado(s))',
                $existia ? 'yellow' : 'green',
                $existia ? 'actualiza' : 'crea     ',
                $slug,
                $render['palabras'],
                count($servicios),
                count($relacionados)
            ));

            if ($this->simulacion) {
                continue;
            }

            DB::transaction(function () use ($slug, $atributos, $servicios) {
                $articulo = Articulo::withTrashed()->firstOrNew(['slug' => $slug]);
                $articulo->fill($atributos);
                $articulo->slug = $slug;
                $articulo->save();

                if ($articulo->trashed()) {
                    $articulo->restore();
                }

                $this->sincronizarServicios($articulo, $servicios);
            });
        }

        // ---------------------------------------------------------------
        // Segunda pasada: relaciones entre articulos.
        // ---------------------------------------------------------------
        // Van aparte porque un articulo puede declarar como relacionado otro
        // que todavia no se habia importado cuando se leyo su fichero.
        $relacionesRotas = 0;

        foreach ($pendientesRelacion as $slug => $destinos) {
            $ids = [];

            foreach ($destinos as $orden => $destino) {
                $id = Articulo::query()->where('slug', $destino)->value('id');

                if (! $id) {
                    $this->warn("  relacion ignorada: {$slug} -> {$destino} (no existe).");
                    $relacionesRotas++;

                    continue;
                }

                $ids[$id] = ['orden' => $orden];
            }

            if ($this->simulacion) {
                continue;
            }

            Articulo::query()->where('slug', $slug)->first()?->relacionados()->sync($ids);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d creado(s), %d actualizado(s), %d saltado(s), %d relacion(es) ignorada(s).',
            $this->simulacion ? 'Se habrian aplicado:' : 'Aplicado:',
            $creados,
            $actualizados,
            $saltados,
            $relacionesRotas
        ));

        return self::SUCCESS;
    }

    /** @param list<string> $slugs */
    private function sincronizarServicios(Articulo $articulo, array $slugs): void
    {
        ArticuloServicio::query()
            ->where('articulo_id', $articulo->id)
            ->when($slugs !== [], fn ($q) => $q->whereNotIn('servicio_slug', $slugs))
            ->delete();

        foreach ($slugs as $slug) {
            ArticuloServicio::query()->firstOrCreate([
                'articulo_id' => $articulo->id,
                'servicio_slug' => $slug,
            ]);
        }
    }

    /** El autor se resuelve por email y, si no, por nombre. Nunca se crea. */
    private function resolverAutor(?string $identificador): ?User
    {
        if (! $identificador) {
            return null;
        }

        return User::query()->where('email', $identificador)->first()
            ?? User::query()->where('name', $identificador)->first();
    }

    // ------------------------------------------------------------------
    // Parseo del front-matter
    // ------------------------------------------------------------------

    /**
     * Lector del front-matter que escribe blog:exportar.
     *
     * No se usa symfony/yaml porque solo esta en packages-dev y produccion se
     * instala con --no-dev. El formato es plano: escalares entrecomillados,
     * `null`, `[]` y listas de una linea por elemento.
     *
     * @return array{meta: array<string, mixed>, cuerpo: string}
     */
    private function parsear(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);

        if (! str_starts_with($contenido, "---\n")) {
            return ['meta' => [], 'cuerpo' => trim($contenido)];
        }

        $resto = substr($contenido, 4);
        $cierre = strpos($resto, "\n---");

        if ($cierre === false) {
            return ['meta' => [], 'cuerpo' => trim($contenido)];
        }

        $bloque = substr($resto, 0, $cierre);
        $cuerpo = ltrim(substr($resto, $cierre + 4), "\n");

        $meta = [];
        $claveLista = null;

        foreach (explode("\n", $bloque) as $linea) {
            if (trim($linea) === '' || str_starts_with(ltrim($linea), '#')) {
                continue;
            }

            if (preg_match('/^\s+-\s*(.*)$/', $linea, $m)) {
                if ($claveLista !== null) {
                    $meta[$claveLista][] = (string) $this->valor($m[1]);
                }

                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $linea, $m)) {
                $clave = $m[1];
                $crudo = trim($m[2]);

                if ($crudo === '') {
                    $meta[$clave] = [];
                    $claveLista = $clave;

                    continue;
                }

                $claveLista = null;
                $meta[$clave] = $this->valor($crudo);
            }
        }

        return ['meta' => $meta, 'cuerpo' => trim($cuerpo)];
    }

    private function valor(string $crudo): mixed
    {
        $crudo = trim($crudo);

        if ($crudo === '[]') {
            return [];
        }

        if ($crudo === 'null' || $crudo === '~' || $crudo === '') {
            return null;
        }

        if (strlen($crudo) >= 2 && str_starts_with($crudo, '"') && str_ends_with($crudo, '"')) {
            $interior = substr($crudo, 1, -1);

            return strtr($interior, [
                '\\n' => "\n",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        if (strlen($crudo) >= 2 && str_starts_with($crudo, "'") && str_ends_with($crudo, "'")) {
            return str_replace("''", "'", substr($crudo, 1, -1));
        }

        return $crudo;
    }
}
