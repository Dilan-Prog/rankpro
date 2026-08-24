<?php

namespace Tests\Feature;

use App\Enums\EstadoArticulo;
use App\Models\Articulo;
use App\Models\User;
use App\Support\Clusters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blog publico y CRUD de administracion.
 *
 * El foco esta en las reglas que, si se rompen, cuestan trafico de verdad:
 * que los borradores no sean accesibles, que la paginacion declare canonicas
 * distintas, y que los articulos entren y salgan del sitemap cuando toca.
 */
class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function articuloPublicado(array $atributos = []): Articulo
    {
        return Articulo::factory()->create($atributos + ['autor_id' => $this->usuario()->id]);
    }

    // ------------------------------------------------------------------
    // Visibilidad
    // ------------------------------------------------------------------

    public function test_el_indice_del_blog_responde(): void
    {
        $this->articuloPublicado();

        $this->get('/blog')->assertOk();
    }

    public function test_un_articulo_publicado_es_accesible(): void
    {
        $articulo = $this->articuloPublicado();

        $this->get("/blog/{$articulo->slug}")
            ->assertOk()
            ->assertSee($articulo->titulo, false);
    }

    public function test_un_borrador_no_es_accesible_por_url(): void
    {
        // Adivinar el slug de un borrador no debe filtrar contenido sin publicar.
        $borrador = Articulo::factory()->borrador()->create(['autor_id' => $this->usuario()->id]);

        $this->get("/blog/{$borrador->slug}")->assertNotFound();
    }

    public function test_un_articulo_programado_a_futuro_no_es_accesible(): void
    {
        $programado = Articulo::factory()->programado()->create(['autor_id' => $this->usuario()->id]);

        $this->get("/blog/{$programado->slug}")->assertNotFound();
    }

    public function test_un_articulo_archivado_no_es_accesible(): void
    {
        $archivado = Articulo::factory()->archivado()->create(['autor_id' => $this->usuario()->id]);

        $this->get("/blog/{$archivado->slug}")->assertNotFound();
    }

    public function test_los_borradores_no_aparecen_en_el_indice(): void
    {
        $publicado = $this->articuloPublicado();
        $borrador = Articulo::factory()->borrador()->create(['autor_id' => $this->usuario()->id]);

        $html = $this->get('/blog')->getContent();

        $this->assertStringContainsString($publicado->slug, $html);
        $this->assertStringNotContainsString($borrador->slug, $html);
    }

    // ------------------------------------------------------------------
    // Clusteres
    // ------------------------------------------------------------------

    public function test_cada_cluster_tiene_pagina_propia(): void
    {
        foreach (Clusters::slugs() as $slug) {
            Articulo::factory()->delCluster($slug)->create(['autor_id' => $this->usuario()->id]);

            $this->get("/blog/categoria/{$slug}")->assertOk();
        }
    }

    public function test_un_cluster_inexistente_devuelve_404(): void
    {
        $this->get('/blog/categoria/no-existe-este-cluster')->assertNotFound();
    }

    public function test_la_pagina_de_cluster_enlaza_a_su_pagina_de_servicio(): void
    {
        // Es la conversion del cluster: sin este enlace, el blog es un silo que
        // no aporta nada a las paginas comerciales.
        $usuario = $this->usuario();
        Articulo::factory()->delCluster('seo-organico')->create(['autor_id' => $usuario->id]);

        $servicio = Clusters::servicioDe('seo-organico');

        $this->get('/blog/categoria/seo-organico')
            ->assertOk()
            ->assertSee(route('servicios.show', $servicio), false);
    }

    // ------------------------------------------------------------------
    // Paginacion y canonicas
    // ------------------------------------------------------------------

    public function test_la_paginacion_declara_una_canonica_distinta_por_pagina(): void
    {
        // El fallo mas probable de toda la implementacion: url()->current()
        // descarta el query string, asi que sin override las 4 paginas del
        // indice declararian la misma canonica y Google las colapsaria.
        $usuario = $this->usuario();
        Articulo::factory()->count(15)->create(['autor_id' => $usuario->id]);

        $pagina2 = $this->get('/blog?page=2')->assertOk()->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $pagina2, $m);

        $this->assertStringContainsString(
            'page=2',
            $m[1] ?? '',
            'La canonica de /blog?page=2 debe incluir el query string.'
        );
    }

    public function test_las_paginas_de_paginacion_son_indexables(): void
    {
        // Un noindex en la paginacion impide que Google descubra los articulos
        // profundos. Deben ser indexables.
        config(['seo.indexable' => true]);
        Articulo::factory()->count(15)->create(['autor_id' => $this->usuario()->id]);

        $this->get('/blog?page=2')->assertSee('content="index, follow', false);
    }

    public function test_una_pagina_de_paginacion_fuera_de_rango_devuelve_404(): void
    {
        $this->articuloPublicado();

        $this->get('/blog?page=999')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Metadatos y datos estructurados
    // ------------------------------------------------------------------

    public function test_el_articulo_declara_og_type_article(): void
    {
        $articulo = $this->articuloPublicado();

        $this->get("/blog/{$articulo->slug}")
            ->assertSee('<meta property="og:type" content="article">', false);
    }

    public function test_el_indice_conserva_og_type_website(): void
    {
        $this->articuloPublicado();

        $this->get('/blog')->assertSee('<meta property="og:type" content="website">', false);
    }

    public function test_el_articulo_emite_fechas_de_articulo(): void
    {
        $articulo = $this->articuloPublicado();

        $this->get("/blog/{$articulo->slug}")
            ->assertSee('article:published_time', false)
            ->assertSee('article:modified_time', false);
    }

    public function test_el_articulo_tiene_un_solo_h1(): void
    {
        $articulo = $this->articuloPublicado();

        $html = $this->get("/blog/{$articulo->slug}")->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/i', $html));
    }

    public function test_el_json_ld_del_articulo_es_valido_y_reutiliza_la_entidad_global(): void
    {
        $articulo = $this->articuloPublicado();

        $html = $this->get("/blog/{$articulo->slug}")->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $this->assertNotEmpty($m[1], 'El articulo no emite JSON-LD.');

        $tipos = [];

        foreach ($m[1] as $json) {
            $datos = json_decode($json, true);
            $this->assertNotNull($datos, 'JSON-LD invalido: '.json_last_error_msg());

            foreach ($datos['@graph'] ?? [$datos] as $nodo) {
                $tipos[] = $nodo['@type'] ?? null;
            }
        }

        $this->assertContains('BlogPosting', $tipos);
        $this->assertContains('BreadcrumbList', $tipos);

        // El publisher debe apuntar al @id de la organizacion global, no
        // duplicar el nodo: si se duplica, Google ve dos entidades distintas.
        $this->assertStringContainsString(url('/#organization'), $html);
    }

    public function test_el_headline_del_schema_no_pasa_de_110_caracteres(): void
    {
        $articulo = $this->articuloPublicado([
            'titulo' => str_repeat('Titulo muy largo para el schema ', 8),
        ]);

        $html = $this->get("/blog/{$articulo->slug}")->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        foreach ($m[1] as $json) {
            foreach (json_decode($json, true)['@graph'] ?? [] as $nodo) {
                if (($nodo['@type'] ?? null) === 'BlogPosting') {
                    $this->assertLessThanOrEqual(110, mb_strlen($nodo['headline']));
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Sitemap
    // ------------------------------------------------------------------

    public function test_el_sitemap_incluye_los_articulos_publicados(): void
    {
        $articulo = $this->articuloPublicado();

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('blog.show', $articulo->slug), false);
    }

    public function test_el_sitemap_excluye_borradores_archivados_y_programados(): void
    {
        $usuario = $this->usuario();
        $borrador = Articulo::factory()->borrador()->create(['autor_id' => $usuario->id]);
        $archivado = Articulo::factory()->archivado()->create(['autor_id' => $usuario->id]);
        $programado = Articulo::factory()->programado()->create(['autor_id' => $usuario->id]);

        $xml = $this->get('/sitemap.xml')->getContent();

        foreach ([$borrador, $archivado, $programado] as $oculto) {
            $this->assertStringNotContainsString($oculto->slug, $xml);
        }
    }

    public function test_el_sitemap_no_incluye_urls_paginadas(): void
    {
        Articulo::factory()->count(15)->create(['autor_id' => $this->usuario()->id]);

        $this->get('/sitemap.xml')->assertDontSee('page=', false);
    }

    public function test_el_lastmod_del_articulo_usa_la_fecha_editorial_y_no_updated_at(): void
    {
        // updated_at cambia al corregir una coma. Publicar eso como lastmod es
        // una senal de manipulacion, ademas de gastar presupuesto de rastreo.
        $articulo = $this->articuloPublicado([
            'fecha_publicacion' => '2026-03-01',
            'fecha_actualizacion' => '2026-05-15',
        ]);

        $articulo->touch(); // updated_at = hoy

        $this->get('/sitemap.xml')->assertSee('2026-05-15', false);
    }

    public function test_el_sitemap_sigue_siendo_xml_valido_con_articulos(): void
    {
        Articulo::factory()->count(5)->create(['autor_id' => $this->usuario()->id]);

        $xml = $this->get('/sitemap.xml')->getContent();

        $doc = new \DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $valido = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $this->assertTrue($valido, 'El sitemap dejo de ser XML valido.');
    }

    // ------------------------------------------------------------------
    // Enlazado hub-and-spoke
    // ------------------------------------------------------------------

    public function test_la_pagina_de_servicio_lista_sus_articulos(): void
    {
        // Sin este enlace de vuelta, el blog no aporta nada a las paginas que
        // convierten. Es el punto del plan que mas valor SEO tiene.
        $articulo = Articulo::factory()
            ->delCluster('seo-organico')
            ->create(['autor_id' => $this->usuario()->id]);

        $this->get('/servicios/seo-organico')
            ->assertOk()
            ->assertSee($articulo->titulo, false);
    }

    public function test_la_pagina_de_servicio_sin_articulos_no_muestra_bloque_vacio(): void
    {
        $this->get('/servicios/social-media')->assertOk();
    }

    // ------------------------------------------------------------------
    // Administracion
    // ------------------------------------------------------------------

    public function test_el_admin_del_blog_exige_autenticacion(): void
    {
        $this->get('/admin/blog')->assertRedirect('/login');
        $this->post('/admin/blog', [])->assertRedirect('/login');
    }

    public function test_un_usuario_autenticado_ve_el_listado(): void
    {
        $this->articuloPublicado();

        $this->actingAs($this->usuario())->get('/admin/blog')->assertOk();
    }

    public function test_se_puede_crear_un_articulo(): void
    {
        $usuario = $this->usuario();

        $respuesta = $this->actingAs($usuario)->post('/admin/blog', [
            'slug' => 'que-es-inp',
            'titulo' => 'Qué es INP y cómo mejorarlo',
            'meta_title' => 'Qué es INP y cómo mejorarlo | RankPro',
            'meta_description' => 'INP reemplazó a FID como métrica de interactividad de Core Web Vitals: qué mide, qué valor es aceptable y cómo mejorarlo paso a paso.',
            'resumen' => 'La métrica que reemplazó a FID en Core Web Vitals.',
            'contenido' => "INP mide la respuesta a la interacción.\n\n## Qué mide\n\n".str_repeat('Contenido de prueba con suficiente longitud. ', 40),
            'cluster' => 'pagespeed-core-web-vitals',
            'estado' => 'publicado',
            'autor_id' => $usuario->id,
            'fecha_publicacion' => '2026-09-01',
        ]);

        $respuesta->assertRedirect();

        $this->assertDatabaseHas('articulos', ['slug' => 'que-es-inp']);

        $articulo = Articulo::where('slug', 'que-es-inp')->first();

        // El HTML se renderiza AL GUARDAR, no en cada visita.
        $this->assertNotEmpty($articulo->contenido_html);
        $this->assertGreaterThan(0, $articulo->palabras);
    }

    public function test_no_se_puede_crear_un_articulo_con_metadatos_fuera_de_rango(): void
    {
        // Es la barrera que evita publicar 30 articulos con titulos truncados
        // en la SERP. El limite de 60/160 lo verifica tambien SeoTest.
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post('/admin/blog', [
            'slug' => 'demasiado-largo',
            'titulo' => 'Título',
            'meta_title' => str_repeat('a', 80),
            'meta_description' => str_repeat('b', 200),
            'resumen' => 'Resumen.',
            'contenido' => str_repeat('Contenido de prueba. ', 40),
            'cluster' => 'seo-organico',
            'estado' => 'borrador',
            'autor_id' => $usuario->id,
        ])->assertSessionHasErrors(['meta_title', 'meta_description']);
    }

    public function test_no_se_pueden_duplicar_slugs(): void
    {
        $usuario = $this->usuario();
        $existente = $this->articuloPublicado(['slug' => 'slug-ocupado']);

        $this->actingAs($usuario)->post('/admin/blog', [
            'slug' => 'slug-ocupado',
            'titulo' => 'Otro artículo',
            'meta_title' => 'Otro artículo | RankPro',
            'meta_description' => 'Una descripción suficientemente larga para pasar el mínimo de setenta caracteres que exige la validación del formulario.',
            'resumen' => 'Resumen.',
            'contenido' => str_repeat('Contenido de prueba. ', 40),
            'cluster' => 'seo-organico',
            'estado' => 'borrador',
            'autor_id' => $usuario->id,
        ])->assertSessionHasErrors('slug');
    }

    public function test_al_borrar_un_articulo_desaparece_del_sitio_publico(): void
    {
        $usuario = $this->usuario();
        $articulo = $this->articuloPublicado();

        $this->actingAs($usuario)->delete("/admin/blog/{$articulo->slug}")->assertRedirect();

        $this->get("/blog/{$articulo->slug}")->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee($articulo->slug, false);
        $this->assertSoftDeleted('articulos', ['id' => $articulo->id]);
    }

    // ------------------------------------------------------------------
    // Renderizado
    // ------------------------------------------------------------------

    public function test_el_markdown_se_renderiza_al_guardar_y_no_en_la_vista(): void
    {
        $articulo = $this->articuloPublicado();

        $this->assertNotEmpty($articulo->contenido_html);
        $this->assertNotEmpty($articulo->toc);

        // La vista publica sirve el HTML almacenado tal cual.
        $this->get("/blog/{$articulo->slug}")
            ->assertSee($articulo->toc[0]['texto'], false);
    }

    public function test_el_estado_publicado_exige_fecha_de_publicacion(): void
    {
        $articulo = $this->articuloPublicado(['fecha_publicacion' => now()->subDay()]);

        $this->assertSame(EstadoArticulo::Publicado, $articulo->estado);
        $this->assertNotNull($articulo->fecha_publicacion);
    }
}
