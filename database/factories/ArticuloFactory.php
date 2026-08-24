<?php

namespace Database\Factories;

use App\Enums\EstadoArticulo;
use App\Models\User;
use App\Support\Clusters;
use App\Support\Contenido\RenderizadorMarkdown;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Articulo>
 */
class ArticuloFactory extends Factory
{
    public function definition(): array
    {
        $titulo = rtrim($this->faker->sentence(6), '.');
        $cluster = $this->faker->randomElement(Clusters::slugs());

        $markdown = $this->markdownDePrueba();
        $render = app(RenderizadorMarkdown::class)->procesar($markdown);

        $publicacion = $this->faker->dateTimeBetween('-8 months', 'now');

        return [
            'slug' => Str::slug($titulo).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'titulo' => $titulo,
            // Los limites de la SERP se respetan tambien en los datos de prueba:
            // si no, los tests de longitud pasarian en verde con datos falsos y
            // fallarian con contenido real.
            'meta_title' => Str::limit($titulo.' | RankPro', 60, ''),
            'meta_description' => Str::limit($this->faker->paragraph(), 155, ''),
            'resumen' => $this->faker->paragraph(2),
            'contenido' => $markdown,
            'contenido_html' => $render['html'],
            'toc' => $render['toc'],
            'palabras' => $render['palabras'],
            'cluster' => $cluster,
            'estado' => EstadoArticulo::Publicado,
            'autor_id' => User::query()->value('id') ?? User::factory(),
            'fecha_publicacion' => $publicacion,
            'fecha_actualizacion' => $publicacion,
            'imagen_destacada' => null,
            'imagen_alt' => null,
            'og_image' => null,
        ];
    }

    public function borrador(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoArticulo::Borrador,
            'fecha_publicacion' => null,
            'fecha_actualizacion' => null,
        ]);
    }

    public function archivado(): static
    {
        return $this->state(fn () => ['estado' => EstadoArticulo::Archivado]);
    }

    /** Publicado con fecha futura: no debe verse ni entrar en el sitemap. */
    public function programado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoArticulo::Publicado,
            'fecha_publicacion' => now()->addWeek(),
        ]);
    }

    public function delCluster(string $cluster): static
    {
        return $this->state(fn () => ['cluster' => $cluster]);
    }

    private function markdownDePrueba(): string
    {
        return <<<'MD'
        Respuesta directa en las primeras lineas, que es lo que extraen los
        fragmentos destacados y los motores de busqueda con IA.

        ## Primera seccion

        Texto de la primera seccion con suficiente longitud para que el conteo de
        palabras y el tiempo de lectura devuelvan algo realista.

        ### Un subapartado

        Mas detalle.

        ## Segunda seccion

        Cierre del articulo.
        MD;
    }
}
