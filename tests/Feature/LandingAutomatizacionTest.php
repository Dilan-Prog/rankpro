<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landing de /servicios/automatizacion-de-procesos (AD-01). */
class LandingAutomatizacionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/automatizacion-de-procesos';

    public function test_lleva_metadatos_y_encabezados(): void
    {
        $html = $this->get(self::URL)->assertOk()
            ->assertSee('<title>Automatización de Procesos Empresariales con n8n | RankPro</title>', false)
            ->assertSeeInOrder([
                'Automatización de procesos para empresas: <em>recupera tu tiempo</em></h1>',
                'Automatiza tus procesos, recupera tu tiempo.',
                'Somos una agencia de automatización de procesos en México.',
                'qué procesos de tu empresa se pueden automatizar',
                'Automatización con n8n</h2>',
                'Cuatro pasos, sin sorpresas',
            ], false)
            ->assertDontSee('[CONFIRMAR')->assertDontSee('[VERIFICAR')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
    }

    /** Las cifras del diagrama no son de un cliente: tienen que leerse como ejemplo. */
    public function test_las_cifras_del_tablero_se_rotulan_como_ejemplo(): void
    {
        $this->get(self::URL)
            ->assertSeeInOrder(['Flujo de ejemplo', 'datos ilustrativos', '428'])
            ->assertDontSee('Flujo activo');
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
        $this->assertCount(8, $v);
    }

    public function test_solo_enlaza_guias_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/n8n-que-es', false)->assertDontSee('guias-servicio', false);

        Articulo::factory()->create(['slug' => 'n8n-que-es']);

        $this->get(self::URL)->assertSee('/blog/n8n-que-es', false)->assertSee('guias-servicio', false);
    }
}
