<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /servicios/desarrollo-de-aplicaciones-moviles (AP-01). */
class LandingAppsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/desarrollo-de-aplicaciones-moviles';

    public function test_metadatos_y_sin_datos_por_confirmar(): void
    {
        preg_match('/<main>(.*)<\/main>/s', $this->get(self::URL)->assertOk()
            ->assertSee('<title>Desarrollo de Aplicaciones Móviles en México | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertSee('"name":"Desarrollo de aplicaciones móviles en México"', false)
            ->assertDontSee('CONFIRMAR')
            ->getContent(), $m);

        // Ni precios ni plazos: no hay datos confirmados.
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d|\d+\s+(semanas|meses|días)/u', strip_tags($m[1]));

        $texto = html_entity_decode(strip_tags(preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/</'], [' ', ' ', ' <'], $m[1])), ENT_QUOTES);
        preg_match_all('/\S*[\p{L}\p{N}]\S*/u', $texto, $palabras);
        $this->assertGreaterThanOrEqual(1200, count($palabras[0]));
    }

    public function test_aparece_en_menu_sitemap_llms_y_desarrollo_web(): void
    {
        $url = url(self::URL);

        $this->get('/nosotros')->assertSee('href="'.$url.'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.$url.'</loc>', false);
        $this->get('/llms.txt')->assertSee($url, false);
        $this->get('/servicios/desarrollo-web')->assertSee('desarrollo de aplicaciones móviles</a>', false);
    }

    public function test_las_7_faq_son_identicas_en_la_pagina_y_el_schema(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $faq = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'FAQPage');
        preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>\s*<p>(.*?)<\/p>/s', $html, $v, PREG_SET_ORDER);

        $this->assertCount(7, $v);
        $this->assertSame(
            array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
            array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $faq['mainEntity']),
        );
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/cuanto-cuesta-crear-una-app', false)->assertDontSee('Guías antes de decidir');

        Articulo::factory()->delCluster('desarrollo-web')->create(['slug' => 'cuanto-cuesta-crear-una-app']);
        $this->get(self::URL)->assertSee('/blog/cuanto-cuesta-crear-una-app', false);
    }
}
