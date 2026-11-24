<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\Servicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landing de /servicios/desarrollo-web (DW-01). */
class LandingDesarrolloWebTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/desarrollo-web';

    public function test_lleva_metadatos_encabezados_y_configurador(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Desarrollo Web y Diseño de Páginas Web en México | RankPro</title>', false)
            ->assertSeeInOrder([
                'Desarrollo web</span> y diseño de páginas web a la medida en México</h1>',
                'Tu sitio, armado a tu medida. Con precio y fecha antes de la junta.',
                'id="configurador"',
                'Qué tipo de sitio necesitas',
                'Diseño de páginas web y desarrollo web en un solo equipo',
                'Desarrollo web cerca de ti',
                'Preguntas frecuentes sobre desarrollo web',
            ], false);
    }

    public function test_configurador_tabla_y_faq_dicen_los_mismos_precios(): void
    {
        $html = $this->get(self::URL)->getContent();

        foreach (Servicios::PRECIOS_WEB as $precio) {
            // Configurador, tabla, FAQ visible y FAQ del schema.
            $this->assertGreaterThanOrEqual(4, substr_count($html, '$'.number_format($precio)));
        }
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

    public function test_solo_enlaza_guias_y_landings_publicadas(): void
    {
        $this->get(self::URL)
            ->assertSee('href="'.url('/servicios/desarrollo-web/cancun').'"', false)
            ->assertDontSee('/blog/cuanto-cuesta-una-pagina-web', false);

        Articulo::factory()->delCluster('desarrollo-web')->create(['slug' => 'cuanto-cuesta-una-pagina-web']);

        $this->get(self::URL)->assertSee('/blog/cuanto-cuesta-una-pagina-web', false);
    }
}
