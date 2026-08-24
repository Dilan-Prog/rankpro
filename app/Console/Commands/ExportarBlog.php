<?php

namespace App\Console\Commands;

use App\Models\Articulo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Vuelca los articulos del blog a Markdown con front-matter en resources/content/blog.
 *
 * Razon de existir: el contenido vive en base de datos, y una base de datos no
 * tiene diff, ni pull request, ni rollback, ni paridad entre local y
 * produccion. Si alguien borra un parrafo en el panel no queda rastro de quien
 * ni de que decia antes, y para llevar los 30 articulos a otro entorno hay que
 * copiar un dump. Exportando a ficheros el contenido entra en git: se revisa en
 * un PR, se revierte con un checkout y se siembra otro entorno con
 * `blog:importar`.
 *
 * No se exportan contenido_html, toc ni palabras: son derivados del Markdown y
 * `blog:importar` los recalcula. Versionarlos generaria un diff enorme y de
 * puro ruido cada vez que alguien guarda un articulo.
 */
class ExportarBlog extends Command
{
    protected $signature = 'blog:exportar
                            {--slug= : Exportar unicamente el articulo con este slug}
                            {--force : Sobrescribir los ficheros existentes sin preguntar}';

    protected $description = 'Exporta los articulos del blog a Markdown con front-matter para versionarlos en git';

    /** Ruta relativa a base_path() donde vive el contenido exportado. */
    public const DIRECTORIO = 'resources/content/blog';

    public function handle(): int
    {
        $destino = base_path(self::DIRECTORIO);

        $articulos = Articulo::query()
            ->with(['autor', 'relacionServicios', 'relacionados'])
            ->when($this->option('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('slug')
            ->get();

        if ($articulos->isEmpty()) {
            $this->warn('No hay articulos que exportar.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($destino);

        $escritos = 0;
        $omitidos = 0;

        foreach ($articulos as $articulo) {
            $fichero = $destino.DIRECTORY_SEPARATOR.$articulo->slug.'.md';

            if (File::exists($fichero) && ! $this->option('force')) {
                // En modo no interactivo confirm() devuelve el valor por
                // defecto (false), asi que en CI hay que pasar --force.
                if (! $this->confirm("Ya existe {$articulo->slug}.md. ¿Sobrescribir?", false)) {
                    $omitidos++;

                    continue;
                }
            }

            File::put($fichero, $this->documento($articulo));
            $escritos++;
            $this->line("  <fg=green>escrito</> {$articulo->slug}.md");
        }

        $this->newLine();
        $this->info("{$escritos} fichero(s) escrito(s) en ".self::DIRECTORIO.'/');

        if ($omitidos > 0) {
            $this->warn("{$omitidos} omitido(s) por no sobrescribir. Usa --force para forzarlos.");
        }

        return self::SUCCESS;
    }

    private function documento(Articulo $articulo): string
    {
        return $this->frontMatter($articulo)."\n".rtrim((string) $articulo->contenido)."\n";
    }

    /**
     * Front-matter YAML generado a mano.
     *
     * symfony/yaml esta en composer.lock unicamente como packages-dev, y
     * produccion se instala con --no-dev: usarlo dejaria el comando roto justo
     * en el entorno del que mas interesa poder exportar. El front-matter es
     * plano (escalares y listas de cadenas), asi que generarlo aqui es barato.
     */
    private function frontMatter(Articulo $articulo): string
    {
        $campos = [
            'titulo' => $articulo->titulo,
            'meta_title' => $articulo->meta_title,
            'meta_description' => $articulo->meta_description,
            'slug' => $articulo->slug,
            'resumen' => $articulo->resumen,
            'cluster' => $articulo->cluster,
            // El autor va por email (o nombre): los ids autoincrementales no
            // coinciden entre local, staging y produccion.
            'autor' => $articulo->autor?->email ?? $articulo->autor?->name,
            'estado' => $articulo->estado?->value,
            'fecha_publicacion' => $articulo->fecha_publicacion?->toDateString(),
            'fecha_actualizacion' => $articulo->fecha_actualizacion?->toDateString(),
            'servicios' => $articulo->relacionServicios->pluck('servicio_slug')->values()->all(),
            'relacionados' => $articulo->relacionados->pluck('slug')->values()->all(),
            'imagen_destacada' => $articulo->imagen_destacada,
            'imagen_alt' => $articulo->imagen_alt,
            'og_image' => $articulo->og_image,
        ];

        $lineas = ['---'];

        foreach ($campos as $clave => $valor) {
            if (is_array($valor)) {
                if ($valor === []) {
                    $lineas[] = "{$clave}: []";

                    continue;
                }

                $lineas[] = "{$clave}:";

                foreach ($valor as $item) {
                    $lineas[] = '  - '.$this->escalar((string) $item);
                }

                continue;
            }

            $lineas[] = "{$clave}: ".($valor === null ? 'null' : $this->escalar((string) $valor));
        }

        $lineas[] = '---';

        return implode("\n", $lineas)."\n";
    }

    /**
     * Escalar YAML seguro: siempre entre comillas dobles.
     *
     * Entrecomillar todo evita tener que decidir si un valor como "no", "2026-01-01"
     * o "12:30" se interpretaria como booleano, fecha o sexagesimal al releerlo.
     */
    private function escalar(string $valor): string
    {
        $valor = str_replace(["\r\n", "\r"], "\n", $valor);
        $valor = strtr($valor, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\t" => '\\t',
        ]);

        return '"'.$valor.'"';
    }
}
