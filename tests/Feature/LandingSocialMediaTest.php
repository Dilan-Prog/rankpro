<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /servicios/social-media (SM-01). */
class LandingSocialMediaTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/social-media';

    public function test_conserva_title_y_h1_y_lleva_las_keywords(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Gestión de Redes Sociales y Pauta Digital | RankPro</title>', false)
            ->assertSee('<h1>Gestión de redes sociales y pauta en Meta</h1>', false)
            ->assertSee('Somos una agencia de redes sociales en México: hacemos el manejo de redes sociales')
            ->assertSee('Un social media manager coordina')
            ->assertSee('social media management')
            ->assertSee('"name":"Gestión de redes sociales y pauta en Meta"', false)
            ->assertSee('Redes sociales por ciudad')
            ->assertSee('href="'.url('/servicios/social-media/monterrey').'"', false)
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

    public function test_la_guia_de_costos_se_enlaza_al_publicarse_tambien_en_la_faq(): void
    {
        $url = url('/blog/cuanto-cuesta-manejo-de-redes-sociales');
        $this->get(self::URL)->assertDontSee($url, false);

        Articulo::factory()->delCluster('social-media')->create(['slug' => 'cuanto-cuesta-manejo-de-redes-sociales']);

        $html = $this->get(self::URL)->assertSee('Antes de contratar, esto te conviene saber')->getContent();
        $this->assertSame(2, substr_count($html, 'href="'.$url.'"'));
    }
}
