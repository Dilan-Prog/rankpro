<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /servicios/pagespeed-core-web-vitals (PY-01). */
class LandingVelocidadTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/pagespeed-core-web-vitals';

    public function test_conserva_title_y_h1_y_suma_los_bloques_nuevos(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Optimización de PageSpeed y Core Web Vitals | RankPro</title>', false)
            ->assertSee('<h1>Optimización de velocidad y Core Web Vitals</h1>', false)
            ->assertSeeInOrder([
                'Mejoramos la velocidad de carga de tu sitio web',
                'Qué medimos realmente: LCP, INP y CLS',
                'Referencia rápida de Core Web Vitals',
                '≤ 2.5 s', '≤ 200 ms', '≤ 0.1',
                'Velocidad desde el desarrollo',
            ])
            ->assertSee('href="'.url('/servicios/desarrollo-web').'">desarrollo web</a>', false)
            ->assertDontSee('CONFIRMAR');

        $this->get('/servicios/desarrollo-web')->assertSee('optimización de velocidad de carga</a>', false);
    }

    public function test_las_6_faq_son_identicas_en_la_pagina_y_el_schema(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $faq = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'FAQPage');
        preg_match_all('/faq__item">\s*<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/s', $html, $v, PREG_SET_ORDER);

        $this->assertCount(6, $v);
        $this->assertSame(
            array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
            array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $faq['mainEntity']),
        );
    }

    public function test_las_guias_solo_se_enlazan_publicadas_y_sin_tarjeta_duplicada(): void
    {
        $this->get(self::URL)->assertDontSee('Antes de contratar, aprende a leer tus datos');

        Articulo::factory()->delCluster('pagespeed-core-web-vitals')->create(['slug' => 'pagespeed-insights']);

        $html = $this->get(self::URL)->assertSee('Antes de contratar, aprende a leer tus datos')->getContent();
        $this->assertSame(1, substr_count($html, 'href="'.url('/blog/pagespeed-insights').'"'));
    }

    public function test_el_sitemap_usa_su_propia_fecha(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertMatchesRegularExpression('#pagespeed-core-web-vitals</loc>\s*<lastmod>2026-11-23#', $xml);
    }
}
