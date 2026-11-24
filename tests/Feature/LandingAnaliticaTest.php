<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /servicios/analytics-data (AY-01). */
class LandingAnaliticaTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/analytics-data';

    public function test_conserva_title_y_h1_y_lleva_la_keyword(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Analítica Digital: GA4, GTM y Dashboards | RankPro</title>', false)
            ->assertSee('<h1>Analítica digital y medición de conversiones</h1>', false)
            ->assertSee('Hacemos analítica web que sirve para decidir')
            ->assertSee('La configuración de Google Analytics va por Google Tag Manager')
            ->assertSee('href="'.url('/servicios/sem-google-ads').'">SEM y Google Ads</a>', false)
            ->assertSee('"name":"Analítica digital y medición de conversiones"', false)
            ->assertDontSee('CONFIRMAR');
    }

    public function test_las_7_faq_son_identicas_en_la_pagina_y_el_schema(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $faq = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'FAQPage');
        preg_match_all('/faq__item">\s*<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/s', $html, $v, PREG_SET_ORDER);

        $this->assertCount(7, $v);
        $this->assertSame(
            array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
            array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $faq['mainEntity']),
        );
    }

    public function test_solo_enlaza_guias_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('Aprende antes de contratar');

        Articulo::factory()->delCluster('analytics-ga4')->create(['slug' => 'google-tag-manager']);

        $this->get(self::URL)
            ->assertSee('Aprende antes de contratar')
            ->assertSee('href="'.url('/blog/google-tag-manager').'"', false);
    }
}
