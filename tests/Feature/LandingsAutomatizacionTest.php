<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\LandingsAutomatizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de automatización por ciudad (AD-07: Puebla). */
class LandingsAutomatizacionTest extends TestCase
{
    use RefreshDatabase;

    private const PADRE = '/servicios/automatizacion-de-procesos';

    private const URL = self::PADRE.'/puebla';

    public function test_lleva_metadatos_y_canonica_a_si_misma(): void
    {
        $html = $this->get(self::URL)->assertOk()
            ->assertSee('<title>Empresas de Automatización en Puebla | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertDontSee('[CONFIRMAR')->assertDontSee('[VERIFICAR')->assertDontSee('[DATO REAL')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->get(self::PADRE.'/no-existe')->assertNotFound();
    }

    public function test_se_enlaza_desde_el_padre_el_sitemap_y_llms(): void
    {
        $url = url(self::URL);

        $this->get(self::PADRE)->assertSee('href="'.$url.'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.$url.'</loc>', false);
        $this->get('/llms.txt')->assertSee($url, false);
    }

    public function test_schema_de_ciudad_y_faq_identica_a_lo_visible(): void
    {
        foreach (LandingsAutomatizacion::todos() as $slug => $landing) {
            $html = $this->get(self::PADRE.'/'.$slug)->getContent();

            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
            $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

            $this->assertSame($landing['area'], $nodos->firstWhere('@type', 'Service')['areaServed']);
            $this->assertStringNotContainsString('LocalBusiness', $html);

            preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>(.*?)<\/div>/s', $html, $v, PREG_SET_ORDER);
            $this->assertSame(
                array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
                array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $nodos->firstWhere('@type', 'FAQPage')['mainEntity']),
            );

            $this->assertLessThanOrEqual(60, mb_strlen($landing['meta_title']));
            $this->assertLessThanOrEqual(155, mb_strlen($landing['meta_description']));
        }
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/chatbot-whatsapp-business', false);
        Articulo::factory()->create(['slug' => 'chatbot-whatsapp-business']);
        $this->get(self::URL)->assertSee('/blog/chatbot-whatsapp-business', false);
    }
}
