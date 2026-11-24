<?php

namespace Tests\Feature;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings hijas de /servicios/sem-google-ads (SY-03). */
class LandingsAdsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/sem-google-ads/publicidad-digital';

    public function test_metadatos_canonica_y_sin_marcadores(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Agencia de Publicidad y Marketing Digital | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertDontSee('CONFIRMAR');

        $this->get('/servicios/sem-google-ads/no-existe')->assertNotFound();
    }

    public function test_se_enlaza_desde_el_padre_sitemap_y_llms(): void
    {
        $this->get('/servicios/sem-google-ads')->assertSee('href="'.url(self::URL).'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.url(self::URL).'</loc>', false);
        $this->get('/llms.txt')->assertSee(url(self::URL), false);
    }

    public function test_faqpage_identico_a_lo_visible(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);
        preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>(.*?)<\/div>/s', $html, $v, PREG_SET_ORDER);

        $this->assertCount(4, $v);
        $this->assertSame(
            array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
            array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $nodos->firstWhere('@type', 'FAQPage')['mainEntity']),
        );
        $this->assertCount(1, $nodos->where('@type', 'Organization'));
    }

    /** Si copia al servicio, los dos compiten por la misma búsqueda. */
    public function test_comparte_menos_del_10_por_ciento_con_el_servicio(): void
    {
        $frases = fn (string $url) => collect(preg_split('/\n\s*|(?<=[.?!:;])\s+| · /u', html_entity_decode(strip_tags(preg_replace(
            ['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/<\/(p|h1|h2|h3|li|div|span|button|a|td|th)>/'],
            [' ', ' ', "\n"],
            preg_match('/<main>(.*)<\/main>/s', $this->get($url)->getContent(), $m) ? $m[1] : '',
        )), ENT_QUOTES)))
            ->map(fn ($f) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $f))))
            ->filter(fn ($f) => count(explode(' ', $f)) >= 4)->unique()->values();

        $propias = $frases(self::URL);
        $comunes = $propias->intersect($frases('/servicios/sem-google-ads'));

        $this->assertLessThan(0.10, $comunes->count() / $propias->count(), $comunes->count().'/'.$propias->count().': '.$comunes->implode(' | '));
    }

    public function test_la_guia_de_costos_se_enlaza_solo_publicada(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/cuanto-cuesta-google-ads', false);
        Articulo::factory()->delCluster('sem-google-ads')->create(['slug' => 'cuanto-cuesta-google-ads']);
        $this->get(self::URL)->assertSee('/blog/cuanto-cuesta-google-ads', false);
    }
}
