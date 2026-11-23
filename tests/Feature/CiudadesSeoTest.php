<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\CiudadesSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de SEO por ciudad (S-03: CDMX). */
class CiudadesSeoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/seo-organico/cdmx';

    public function test_lleva_metadatos_y_canonica_a_si_misma(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Agencia SEO en CDMX: Posicionamiento Web | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertSee('Agencia SEO en CDMX: posiciona tu empresa en Google')
            ->assertSee('no de CDMX')
            ->assertDontSee('CONFIRMAR');
    }

    public function test_una_ciudad_no_dada_de_alta_es_404(): void
    {
        $this->get('/servicios/seo-organico/monterrey')->assertNotFound();
        $this->get('/servicios/sem-google-ads/cdmx')->assertNotFound();
    }

    public function test_se_enlaza_desde_el_padre_el_pie_el_sitemap_y_llms(): void
    {
        $url = url(self::URL);

        // Bloque de cobertura y pie.
        $html = $this->get('/servicios/seo-organico')->getContent();
        $this->assertSame(2, substr_count($html, 'href="'.$url.'"'));

        $this->get('/nosotros')->assertSee('href="'.$url.'"', false);
        $this->get('/llms.txt')->assertSee($url, false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.$url.'</loc>', false);
    }

    public function test_el_schema_es_de_ciudad_sin_negocio_local_y_con_las_faq_visibles(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

        $this->assertSame(
            ['@type' => 'City', 'name' => 'Ciudad de México'],
            $nodos->firstWhere('@type', 'Service')['areaServed'],
        );
        $this->assertCount(4, $nodos->firstWhere('@type', 'BreadcrumbList')['itemListElement']);
        $this->assertCount(1, $nodos->where('@type', 'Organization'));
        $this->assertStringNotContainsString('LocalBusiness', $html);

        $visible = strip_tags(preg_replace('/<script.*?<\/script>/s', '', $html));
        foreach ($nodos->firstWhere('@type', 'FAQPage')['mainEntity'] as $pregunta) {
            $this->assertStringContainsString(e($pregunta['acceptedAnswer']['text']), $visible);
        }
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/seo-local', false);

        Articulo::factory()->delCluster('seo-organico')->create(['slug' => 'seo-local']);

        $this->get(self::URL)->assertSee('/blog/seo-local', false);
    }

    public function test_las_metas_caben_en_la_serp(): void
    {
        foreach (CiudadesSeo::todos() as $ciudad) {
            $this->assertLessThanOrEqual(60, mb_strlen($ciudad['meta_title']));
            $this->assertLessThanOrEqual(155, mb_strlen($ciudad['meta_description']));
        }
    }
}
