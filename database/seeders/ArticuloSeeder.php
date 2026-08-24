<?php

namespace Database\Seeders;

use App\Enums\EstadoArticulo;
use App\Models\Articulo;
use App\Models\ArticuloServicio;
use App\Models\User;
use App\Support\Clusters;
use App\Support\Contenido\RenderizadorMarkdown;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de desarrollo del blog: 2 publicados por cluster, mas un borrador,
 * un archivado y uno programado a futuro.
 *
 * Sigue el patron del resto de seeders del proyecto (updateOrCreate por clave
 * natural, aqui el slug), asi que se puede volver a ejecutar sin duplicar.
 *
 * No crea usuarios: reutiliza el primero que exista. Si la tabla users esta
 * vacia lo dice y se detiene, porque inventar un autor a ciegas dejaria
 * articulos firmados por una cuenta fantasma que despues nadie sabe borrar.
 * Ejecuta antes UserSeeder.
 */
class ArticuloSeeder extends Seeder
{
    public function run(): void
    {
        $autor = User::query()->orderBy('id')->first();

        if (! $autor) {
            $this->command?->error(
                'ArticuloSeeder: no hay ningun usuario en la tabla users y los articulos '
                .'necesitan autor_id. Ejecuta antes: php artisan db:seed --class=UserSeeder'
            );

            return;
        }

        $renderizador = app(RenderizadorMarkdown::class);
        $faker = \Faker\Factory::create('es_ES');

        // Publicados: dos por cluster, enlazados entre si.
        foreach (Clusters::todos() as $slugCluster => $cluster) {
            $pareja = [];

            foreach ([1, 2] as $n) {
                $pareja[] = $this->crear(
                    renderizador: $renderizador,
                    faker: $faker,
                    autor: $autor,
                    cluster: $slugCluster,
                    sufijo: "guia-{$n}",
                    titulo: "{$cluster['nombre_corto']}: guia practica {$n}",
                    estado: EstadoArticulo::Publicado,
                    publicacion: now()->subMonths(6 - $n)->toDateString(),
                );
            }

            // Cada uno apunta al otro: cubre el bloque de "sigue leyendo" y
            // evita el aviso de spoke aislado.
            $pareja[0]->relacionados()->sync([$pareja[1]->id => ['orden' => 0]]);
            $pareja[1]->relacionados()->sync([$pareja[0]->id => ['orden' => 0]]);
        }

        // Estados no publicos, uno de cada, para que las vistas y el sitemap
        // tengan casos que filtrar.
        $this->crear(
            renderizador: $renderizador, faker: $faker, autor: $autor,
            cluster: 'seo-organico', sufijo: 'borrador',
            titulo: 'SEO: articulo en borrador',
            estado: EstadoArticulo::Borrador, publicacion: null,
        );

        $this->crear(
            renderizador: $renderizador, faker: $faker, autor: $autor,
            cluster: 'social-media', sufijo: 'archivado',
            titulo: 'Redes: articulo archivado',
            estado: EstadoArticulo::Archivado, publicacion: now()->subYear()->toDateString(),
        );

        $this->crear(
            renderizador: $renderizador, faker: $faker, autor: $autor,
            cluster: 'sem-google-ads', sufijo: 'programado',
            titulo: 'Google Ads: articulo programado',
            estado: EstadoArticulo::Publicado, publicacion: now()->addWeeks(2)->toDateString(),
        );

        $this->command?->info('ArticuloSeeder: 15 articulos sembrados (autor: '.$autor->email.').');
    }

    private function crear(
        RenderizadorMarkdown $renderizador,
        \Faker\Generator $faker,
        User $autor,
        string $cluster,
        string $sufijo,
        string $titulo,
        EstadoArticulo $estado,
        ?string $publicacion,
    ): Articulo {
        $slug = Str::slug($cluster.'-'.$sufijo);
        $servicio = Clusters::todos()[$cluster]['servicio'];

        $markdown = $this->markdown($faker, $cluster, $servicio);
        $render = $renderizador->procesar($markdown);

        $articulo = Articulo::updateOrCreate(
            ['slug' => $slug],
            [
                'titulo' => $titulo,
                'meta_title' => Str::limit($titulo.' | RankPro', 60, ''),
                'meta_description' => Str::limit(
                    'Guia practica sobre '.mb_strtolower($titulo).' con criterios de medicion, '
                    .'errores frecuentes y como priorizar el trabajo mes a mes.',
                    155,
                    ''
                ),
                'resumen' => $faker->paragraph(3),
                'contenido' => $markdown,
                'contenido_html' => $render['html'],
                'toc' => $render['toc'],
                'palabras' => $render['palabras'],
                'cluster' => $cluster,
                'estado' => $estado,
                'autor_id' => $autor->id,
                'fecha_publicacion' => $publicacion,
                'fecha_actualizacion' => $publicacion,
                'imagen_destacada' => "/img/blog/{$slug}.webp",
                'imagen_alt' => $titulo,
                'og_image' => "/img/blog/{$slug}-og.webp",
            ]
        );

        // El enlace al hub comercial es la razon de ser del cluster: sin el,
        // el articulo es trafico que no llega a ninguna pagina que convierte.
        ArticuloServicio::firstOrCreate([
            'articulo_id' => $articulo->id,
            'servicio_slug' => $servicio,
        ]);

        return $articulo;
    }

    /**
     * Markdown de longitud realista (1500-2500 palabras, el estandar que exige
     * blog:validar) con enlaces internos vivos al hub y al indice del cluster.
     */
    private function markdown(\Faker\Generator $faker, string $cluster, string $servicio): string
    {
        $partes = [
            $faker->paragraph(4),
            '',
            'Si quieres el trabajo hecho, revisa el servicio de '
            ."[{$servicio}](/servicios/{$servicio}) o el resto de articulos de "
            ."[este tema](/blog/categoria/{$cluster}).",
            '',
        ];

        $secciones = ['Que se mide y con que', 'Errores frecuentes', 'Como priorizar', 'Preguntas habituales'];

        foreach ($secciones as $i => $seccion) {
            $partes[] = "## {$seccion}";
            $partes[] = '';

            foreach (range(1, 4) as $p) {
                $partes[] = $faker->paragraph(12);
                $partes[] = '';
            }

            if ($i < 2) {
                $partes[] = '### Detalle adicional';
                $partes[] = '';
                $partes[] = $faker->paragraph(10);
                $partes[] = '';
            }
        }

        $markdown = implode("\n", $partes);

        // Se rellena hasta entrar en el rango editorial en lugar de fijar un
        // numero de parrafos: los parrafos de Faker varian mucho de longitud.
        while (str_word_count(strip_tags($markdown)) < 1600) {
            $markdown .= "\n".$faker->paragraph(12)."\n";
        }

        return $markdown;
    }
}
