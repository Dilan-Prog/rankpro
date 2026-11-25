<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\LandingsApps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de aplicaciones móviles por ciudad (AP-09: Querétaro). */
class LandingsAppsTest extends TestCase
{
    use RefreshDatabase;

    private const PADRE = '/servicios/desarrollo-de-aplicaciones-moviles';

    private const URL = self::PADRE.'/queretaro';

    public function test_lleva_metadatos_y_canonica_a_si_misma(): void
    {
        $html = $this->get(self::URL)->assertOk()
            ->assertSee('<title>Desarrollo de Apps en Querétaro | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertDontSee('CONFIRMAR')->assertDontSee('VERIFICAR')->assertDontSee('DATO REAL')
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
        foreach (LandingsApps::todos() as $slug => $landing) {
            $html = $this->get(self::PADRE.'/'.$slug)->getContent();

            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
            $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

            $this->assertSame($landing['area'], $nodos->firstWhere('@type', 'Service')['areaServed']);
            $this->assertCount(4, $nodos->firstWhere('@type', 'BreadcrumbList')['itemListElement']);
            $this->assertStringNotContainsString('LocalBusiness', $html);

            preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>\s*<p>(.*?)<\/p>/s', $html, $v, PREG_SET_ORDER);
            $this->assertCount(5, $v);
            $this->assertSame(
                array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
                array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $nodos->firstWhere('@type', 'FAQPage')['mainEntity']),
            );

            $this->assertLessThanOrEqual(60, mb_strlen($landing['meta_title']));
            $this->assertLessThanOrEqual(155, mb_strlen($landing['meta_description']));
        }
    }

    public function test_al_menos_750_palabras_sin_precios_ni_plazos(): void
    {
        preg_match('/<main>(.*)<\/main>/s', $this->get(self::URL)->getContent(), $m);
        $texto = html_entity_decode(strip_tags(preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/</'], [' ', ' ', ' <'], $m[1])), ENT_QUOTES);
        preg_match_all('/\S*[\p{L}\p{N}]\S*/u', $texto, $palabras);

        $this->assertGreaterThanOrEqual(750, count($palabras[0]));
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d|\d+\s+(semanas|meses|días)/u', $texto);
    }

    /** Regla del plan: no más de ~10 % de frases iguales con otras páginas. */
    public function test_no_repite_frases_del_padre_ni_de_las_otras_landings_de_queretaro(): void
    {
        $frases = function (string $url): array {
            preg_match('/<main>(.*)<\/main>/s', $this->get($url)->getContent(), $m);
            $texto = preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s'], ' ', $m[1]);
            $texto = html_entity_decode(strip_tags(preg_replace('/<\/(p|h1|h2|h3|li|div|span|button|a)>/', "\n", $texto)), ENT_QUOTES);

            return collect(preg_split('/\n\s*|(?<=[.?!:;])\s+| · /u', $texto))
                ->map(fn ($f) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $f))))
                ->filter(fn ($f) => count(explode(' ', $f)) >= 4)
                ->unique()->values()->all();
        };

        $propias = $frases(self::URL);

        foreach ([self::PADRE, '/servicios/desarrollo-web/queretaro', '/servicios/seo-organico/queretaro'] as $otra) {
            $comunes = array_intersect($propias, $frases($otra));
            $this->assertLessThan(0.10, count($comunes) / count($propias), $otra.': '.implode(' | ', $comunes));
        }
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/cuanto-cuesta-crear-una-app', false);
        Articulo::factory()->delCluster('desarrollo-web')->create(['slug' => 'cuanto-cuesta-crear-una-app']);
        $this->get(self::URL)->assertSee('/blog/cuanto-cuesta-crear-una-app', false);
    }
}
