<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landing de /servicios/sem-google-ads (SY-01). */
class LandingGoogleAdsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/sem-google-ads';

    public function test_lleva_metadatos_y_encabezados(): void
    {
        $html = $this->get(self::URL)->assertOk()
            ->assertSee('<title>Agencia de Google Ads en México | Campañas SEM | RankPro</title>', false)
            ->assertSeeInOrder([
                'Agencia de Google Ads en México: deja de pagar clics, paga <span class="text-brand">ventas</span></h1>',
                'Deja de pagar clics. Empieza a pagar ventas.',
                'Somos una agencia de Google Ads en México.',
                'data-cv-calc',
                'Campañas de Google Ads que gestionamos',
                'Cómo es trabajar con nuestra agencia de Google Ads',
            ], false)
            ->assertDontSee('[VERIFICAR')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);
        $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($m[1], ENT_QUOTES)));
    }

    /** Las cifras del simulador no son resultados: el aviso va pegado a ellas. */
    public function test_el_simulador_se_rotula_como_ilustrativo_junto_al_resultado(): void
    {
        $this->get(self::URL)->assertSeeInOrder([
            'Proyección a 90 días · Estimación ilustrativa',
            'data-out-roas',
            'Estimación ilustrativa, no una promesa de resultados.',
        ], false);
    }

    public function test_el_faqpage_es_identico_a_lo_visible(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $faq = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'FAQPage');

        preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>(.*?)<\/div>/s', $html, $v, PREG_SET_ORDER);

        $this->assertSame(
            array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
            array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $faq['mainEntity']),
        );
        $this->assertCount(10, $v);
    }

    public function test_solo_enlaza_guias_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/ejemplos-de-google-ads', false)->assertDontSee('guias-servicio', false);

        Articulo::factory()->delCluster('sem-google-ads')->create(['slug' => 'ejemplos-de-google-ads']);

        $this->get(self::URL)->assertSee('/blog/ejemplos-de-google-ads', false)->assertSee('guias-servicio', false);
    }
}
