<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\LandingsSocial;
use App\Support\Servicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de /servicios/social-media por ciudad (SM-06: Monterrey). */
class LandingsSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadatos_canonica_y_sin_marcadores(): void
    {
        $url = '/servicios/social-media/monterrey';

        $this->get($url)->assertOk()
            ->assertSee('<title>Agencia de Redes Sociales en Monterrey | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url($url).'">', false)
            ->assertDontSee('[CONFIRMAR')->assertDontSee('[VERIFICAR')->assertDontSee('[DATO REAL');

        $this->get('/servicios/social-media/no-existe')->assertNotFound();
    }

    public function test_cada_landing_se_enlaza_desde_el_padre_sitemap_y_llms(): void
    {
        $rutas = array_filter(Servicios::todos()['social-media']['landings']);

        foreach (LandingsSocial::todos() as $slug => $landing) {
            $this->assertSame('servicios/social-media/'.$slug, $rutas[$landing['enlace']] ?? null);
            $this->get('/sitemap.xml')->assertSee('<loc>'.url('/servicios/social-media/'.$slug).'</loc>', false);
            $this->get('/llms.txt')->assertSee(url('/servicios/social-media/'.$slug), false);
        }
    }

    public function test_al_menos_750_palabras_visibles_y_metas_en_rango(): void
    {
        foreach (LandingsSocial::todos() as $slug => $landing) {
            preg_match('/<main>(.*)<\/main>/s', $this->get('/servicios/social-media/'.$slug)->getContent(), $m);
            $texto = html_entity_decode(strip_tags(preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/</'], [' ', ' ', ' <'], $m[1])), ENT_QUOTES);
            $palabras = count(preg_split('/\s+/u', trim($texto)));

            $this->assertGreaterThanOrEqual(750, $palabras, "{$slug}: {$palabras}");
            $this->assertLessThanOrEqual(60, mb_strlen($landing['meta_title']));
            $this->assertLessThanOrEqual(155, mb_strlen($landing['meta_description']));
        }
    }

    public function test_faqpage_identico_a_lo_visible_y_sin_negocio_local(): void
    {
        foreach (LandingsSocial::todos() as $slug => $landing) {
            $html = $this->get('/servicios/social-media/'.$slug)->getContent();

            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
            $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);
            preg_match_all('/cv-faq__trigger.*?<span>(.*?)<\/span>.*?cv-faq__panel[^>]*>\s*<p>(.*?)<\/p>/s', $html, $v, PREG_SET_ORDER);

            $this->assertSame(
                array_map(fn ($x) => [html_entity_decode($x[1], ENT_QUOTES), html_entity_decode($x[2], ENT_QUOTES)], $v),
                array_map(fn ($q) => [$q['name'], $q['acceptedAnswer']['text']], $nodos->firstWhere('@type', 'FAQPage')['mainEntity']),
            );
            $this->assertSame($landing['area'], $nodos->firstWhere('@type', 'Service')['areaServed']);
            $this->assertStringNotContainsString('LocalBusiness', $html);
        }
    }

    /** Entre landings y frente a la página padre. */
    public function test_comparte_menos_del_10_por_ciento(): void
    {
        $urls = array_merge(['/servicios/social-media'], array_map(fn ($s) => '/servicios/social-media/'.$s, array_keys(LandingsSocial::todos())));
        $frases = [];

        foreach ($urls as $url) {
            preg_match('/<main>(.*)<\/main>/s', $this->get($url)->getContent(), $m);
            $texto = preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s', '/<\/(p|h1|h2|h3|li|div|span|button|a)>/'], [' ', ' ', "\n"], $m[1]);
            $frases[$url] = collect(preg_split('/\n\s*|(?<=[.?!:;])\s+| · /u', html_entity_decode(strip_tags($texto), ENT_QUOTES)))
                ->map(fn ($f) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $f))))
                ->filter(fn ($f) => count(explode(' ', $f)) >= 4)->unique()->values();
        }

        foreach ($frases as $a => $deA) {
            foreach (array_diff_key($frases, [$a => true]) as $b => $deB) {
                $comunes = $deA->intersect($deB);
                $this->assertLessThan(0.10, $comunes->count() / $deA->count(), $comunes->count().'/'.$deA->count()." {$a} {$b}: ".$comunes->implode(' | '));
            }
        }
    }

    public function test_guadalajara_lleva_metadatos_afines_y_enlace_a_analitica(): void
    {
        $this->get('/servicios/social-media/guadalajara')->assertOk()
            ->assertSee('<title>Manejo de Redes Sociales en Guadalajara | RankPro</title>', false)
            ->assertSee('href="'.url('/servicios/social-media/monterrey').'"', false)
            ->assertSee('href="'.url('/servicios/analytics-data').'">Google Analytics 4', false)
            ->assertDontSee('[CONFIRMAR')->assertDontSee('[VERIFICAR')->assertDontSee('[DATO REAL');

        $this->get('/servicios/social-media/monterrey')->assertSee('href="'.url('/servicios/social-media/guadalajara').'"', false);
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get('/servicios/social-media/monterrey')->assertDontSee('/blog/community-manager', false);
        Articulo::factory()->delCluster('social-media')->create(['slug' => 'community-manager']);
        $this->get('/servicios/social-media/monterrey')->assertSee('/blog/community-manager', false);
    }
}
