<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\LandingsWeb;
use App\Support\Servicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de desarrollo web por ciudad o sector (DW-05: Cancún). */
class LandingsWebTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/desarrollo-web/cancun';

    public function test_lleva_metadatos_y_canonica_a_si_misma(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Diseño de Páginas Web en Cancún | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertSee('no los presentamos como clientes de Cancún')
            ->assertDontSee('[VERIFICAR')->assertDontSee('[DATO REAL')->assertDontSee('[CONFIRMAR');

        $this->get('/servicios/desarrollo-web/no-existe')->assertNotFound();
    }

    public function test_se_enlaza_desde_el_padre_el_sitemap_y_llms(): void
    {
        $url = url(self::URL);

        $this->get('/servicios/desarrollo-web')->assertSee('href="'.$url.'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.$url.'</loc>', false);
        $this->get('/llms.txt')->assertSee($url, false);
    }

    public function test_schema_de_ciudad_y_faq_identica_a_lo_visible(): void
    {
        foreach (LandingsWeb::todos() as $slug => $landing) {
            $html = $this->get('/servicios/desarrollo-web/'.$slug)->getContent();

            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
            $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

            $this->assertSame($landing['area'], $nodos->firstWhere('@type', 'Service')['areaServed']);
            $this->assertCount(1, $nodos->where('@type', 'Organization'));
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

    /** Mínimo del texto visible en las landings de ciudad, incluida la de automatización. */
    public function test_cada_landing_tiene_al_menos_800_palabras(): void
    {
        $urls = array_map(fn ($slug) => '/servicios/desarrollo-web/'.$slug, array_keys(LandingsWeb::todos()));

        foreach ([...$urls, '/servicios/automatizacion-de-procesos/puebla'] as $url) {
            preg_match('/<main>(.*)<\/main>/s', $this->get($url)->getContent(), $m);
            $texto = html_entity_decode(strip_tags(preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/</'], [' ', ' ', ' <'], $m[1])), ENT_QUOTES);
            preg_match_all('/\S*[\p{L}\p{N}]\S*/u', $texto, $palabras);

            $this->assertGreaterThanOrEqual(800, count($palabras[0]), $url);
        }
    }

    /** Regla del plan: no más de ~10 % de frases iguales entre landings. */
    public function test_dos_landings_comparten_menos_del_10_por_ciento(): void
    {
        $frases = [];

        foreach (array_keys(LandingsWeb::todos()) as $slug) {
            preg_match('/<main>(.*)<\/main>/s', $this->get('/servicios/desarrollo-web/'.$slug)->getContent(), $m);

            $texto = preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s'], ' ', $m[1]);
            $texto = html_entity_decode(strip_tags(preg_replace('/<\/(p|h1|h2|h3|li|div|span|button|a)>/', "\n", $texto)), ENT_QUOTES);

            $frases[$slug] = collect(preg_split('/\n\s*|(?<=[.?!:;])\s+| · /u', $texto))
                ->map(fn ($f) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $f))))
                ->filter(fn ($f) => count(explode(' ', $f)) >= 4)
                ->unique()->values()->all();
        }

        foreach ($frases as $a => $deA) {
            foreach (array_diff_key($frases, [$a => true]) as $b => $deB) {
                $comunes = array_intersect($deA, $deB);
                $this->assertLessThan(0.10, count($comunes) / count($deA), count($comunes).'/'.count($deA)." {$a}-{$b}: ".implode(' | ', $comunes));
            }
        }
    }

    public function test_toda_landing_recibe_enlace_del_padre_y_solo_enlaza_afines(): void
    {
        $padre = $this->get('/servicios/desarrollo-web')->getContent();

        foreach (LandingsWeb::todos() as $slug => $landing) {
            $this->assertStringContainsString('href="'.url('/servicios/desarrollo-web/'.$slug).'"', $padre);
            $this->assertLessThanOrEqual(3, count(LandingsWeb::enlaces($slug)));
            $this->assertNotContains($slug, $landing['afines']);
        }
    }

    public function test_cdmx_enlaza_a_su_landing_de_seo_y_al_servicio(): void
    {
        $this->get('/servicios/desarrollo-web/cdmx')->assertOk()
            ->assertSee('href="'.url('/servicios/seo-organico/cdmx').'">SEO en CDMX', false)
            ->assertSee('servicio de SEO</a>', false);
    }

    public function test_queretaro_enlaza_a_su_landing_de_seo_y_a_monterrey(): void
    {
        $this->get('/servicios/desarrollo-web/queretaro')->assertOk()
            ->assertSee('<title>Diseño de Páginas Web en Querétaro | RankPro</title>', false)
            ->assertSee('href="'.url('/servicios/seo-organico/queretaro').'">SEO en Querétaro', false)
            ->assertSee('href="'.url('/servicios/desarrollo-web/monterrey').'"', false)
            ->assertDontSee('[VERIFICAR')->assertDontSee('[DATO REAL');
    }

    /** Fratelli se cita por su resultado en búsqueda, no por su ocupación ni como desarrollo. */
    public function test_hoteles_cita_a_fratelli_solo_por_busqueda(): void
    {
        $this->get('/servicios/desarrollo-web/hoteles')->assertOk()
            ->assertSee('<title>Diseño de Páginas Web para Hoteles | RankPro</title>', false)
            ->assertSee('Hotel Fratelli: resultado en búsqueda')
            ->assertDontSee('10 % a 50 %')
            ->assertSee('href="'.url('/servicios/seo-organico').'"', false)
            ->assertSee('href="'.url('/servicios/desarrollo-web/cancun').'"', false)
            ->assertDontSee('[VERIFICAR')->assertDontSee('[CONFIRMAR')->assertDontSee('[DATO REAL');

        $this->get(self::URL)->assertSee('href="'.url('/servicios/desarrollo-web/hoteles').'"', false);
    }

    public function test_los_precios_salen_del_catalogo(): void
    {
        $html = $this->get(self::URL)->getContent();

        foreach ([Servicios::PRECIO_LANDING_SENCILLA, ...array_values(Servicios::PRECIOS_WEB)] as $precio) {
            $this->assertStringContainsString('$'.number_format($precio), $html);
        }
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/seo-local', false);
        Articulo::factory()->delCluster('seo-organico')->create(['slug' => 'seo-local']);
        $this->get(self::URL)->assertSee('/blog/seo-local', false);
    }
}
