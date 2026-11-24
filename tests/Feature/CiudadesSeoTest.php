<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\CiudadesSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Landings de SEO por ciudad (S-03: CDMX). */
class CiudadesSeoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/seo-organico/cdmx';

    public function test_lleva_metadatos_y_canonica_a_si_misma(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Agencia SEO en CDMX: Posicionamiento Web | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url(self::URL).'">', false)
            ->assertSee('Agencia SEO en CDMX: posiciona tu empresa en Google')
            ->assertDontSee('CONFIRMAR');
    }

    /** Una ciudad que solo cambia el nombre sería contenido casi duplicado. */
    public function test_monterrey_tiene_texto_propio_y_se_cruza_con_cdmx(): void
    {
        $monterrey = '/servicios/seo-organico/monterrey';

        $this->get($monterrey)->assertOk()
            ->assertSee('<link rel="canonical" href="'.url($monterrey).'">', false)
            ->assertSee('Posicionamiento SEO en Monterrey: qué hay que tener en cuenta')
            ->assertSee('San Pedro Garza García')
            ->assertDontSee('Polanco')
            ->assertSee('href="'.url(self::URL).'"', false);

        $this->get(self::URL)->assertSee('href="'.url($monterrey).'"', false);
    }

    public function test_guadalajara_tiene_texto_propio_y_enlaza_a_las_otras_ciudades(): void
    {
        $guadalajara = '/servicios/seo-organico/guadalajara';

        $this->get($guadalajara)->assertOk()
            ->assertSee('<link rel="canonical" href="'.url($guadalajara).'">', false)
            ->assertSee('Tlajomulco')
            ->assertDontSee('San Pedro Garza')
            ->assertDontSee('/blog/seo-local', false)
            ->assertSee('href="'.url('/servicios/seo-organico/monterrey').'"', false);

        Articulo::factory()->delCluster('seo-organico')->create(['slug' => 'seo-local']);

        $this->get($guadalajara)->assertSee('Más en nuestra guía');
    }

    public function test_puebla_apunta_a_posicionamiento_seo_y_tiene_texto_propio(): void
    {
        $puebla = '/servicios/seo-organico/puebla';

        $this->get($puebla)->assertOk()
            ->assertSee('<title>Posicionamiento SEO en Puebla: Agencia SEO | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url($puebla).'">', false)
            ->assertSee('"name":"Posicionamiento SEO en Puebla"', false)
            ->assertSee('Servicio de posicionamiento SEO para empresas en Puebla')
            ->assertSee('San Pedro Cholula')
            ->assertSee('está en Aguascalientes y no en Puebla')
            ->assertDontSee('Tlajomulco');
    }

    public function test_queretaro_tiene_texto_propio(): void
    {
        $queretaro = '/servicios/seo-organico/queretaro';

        $this->get($queretaro)->assertOk()
            ->assertSee('<title>Agencia SEO en Querétaro: Posicionamiento Web | RankPro</title>', false)
            ->assertSee('<link rel="canonical" href="'.url($queretaro).'">', false)
            ->assertSee('Corregidora')
            ->assertDontSee('Cholula');
    }

    /** Marcadores de trabajo y datos por confirmar nunca se publican. */
    public function test_ninguna_landing_publica_marcadores_ni_omite_los_plazos(): void
    {
        foreach (array_keys(CiudadesSeo::todos()) as $slug) {
            $this->get('/servicios/seo-organico/'.$slug)->assertOk()
                ->assertDontSee('[DATO REAL')
                ->assertDontSee('[VERIFICAR')
                ->assertSee('os cambios suelen notarse entre el mes 3 y el 6');

            $this->assertCount(6, CiudadesSeo::encontrar($slug)['faqs']);
        }
    }

    /**
     * Frases idénticas (4 palabras o más) entre cada par, sobre todo el <main>
     * sin migas: incluye botones y enlaces comunes, que son la base inevitable.
     */
    public function test_dos_landings_no_comparten_el_15_por_ciento_de_sus_frases(): void
    {
        $frases = [];

        foreach (array_keys(CiudadesSeo::todos()) as $slug) {
            preg_match('/<main>(.*)<\/main>/s', $this->get('/servicios/seo-organico/'.$slug)->getContent(), $m);

            $texto = preg_replace(['/<nav.*?<\/nav>/s', '/<svg.*?<\/svg>/s'], ' ', $m[1]);
            $texto = preg_replace('/<\/(p|h1|h2|h3|li|div|span|button)>/', "\n", $texto);
            $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES);

            $frases[$slug] = collect(preg_split('/\n\s*|(?<=[.?!:;])\s+| · /u', $texto))
                ->map(fn ($f) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $f))))
                ->filter(fn ($f) => count(explode(' ', $f)) >= 4)
                ->unique()->values()->all();
        }

        foreach ($frases as $a => $deA) {
            foreach ($frases as $b => $deB) {
                if ($a === $b) {
                    continue;
                }

                $comunes = array_intersect($deA, $deB);
                $this->assertLessThan(
                    0.15,
                    count($comunes) / count($deA),
                    "{$a} comparte demasiado con {$b}: ".implode(' | ', $comunes),
                );
            }
        }
    }

    public function test_una_ciudad_no_dada_de_alta_es_404(): void
    {
        $this->get('/servicios/seo-organico/cancun')->assertNotFound();
        $this->get('/servicios/sem-google-ads/cdmx')->assertNotFound();
    }

    public function test_se_enlaza_desde_el_padre_el_pie_el_sitemap_y_llms(): void
    {
        $url = url(self::URL);

        // Bloque de cobertura y pie.
        $html = $this->get('/servicios/seo-organico')->getContent();
        $this->assertSame(2, substr_count($html, 'href="'.$url.'"'));

        $this->get('/nosotros')->assertSee('href="'.$url.'"', false);
        $this->get('/llms.txt')->assertSee($url, false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.$url.'</loc>', false);
    }

    public function test_el_schema_es_de_ciudad_sin_negocio_local_y_con_las_faq_visibles(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

        $this->assertSame(
            ['@type' => 'City', 'name' => 'Ciudad de México'],
            $nodos->firstWhere('@type', 'Service')['areaServed'],
        );
        $this->assertCount(4, $nodos->firstWhere('@type', 'BreadcrumbList')['itemListElement']);
        $this->assertCount(1, $nodos->where('@type', 'Organization'));
        $this->assertStringNotContainsString('LocalBusiness', $html);

        $visible = strip_tags(preg_replace('/<script.*?<\/script>/s', '', $html));
        foreach ($nodos->firstWhere('@type', 'FAQPage')['mainEntity'] as $pregunta) {
            $this->assertStringContainsString(e($pregunta['acceptedAnswer']['text']), $visible);
        }
    }

    public function test_las_guias_solo_se_enlazan_publicadas(): void
    {
        $this->get(self::URL)->assertDontSee('/blog/seo-local', false);

        Articulo::factory()->delCluster('seo-organico')->create(['slug' => 'seo-local']);

        $this->get(self::URL)->assertSee('/blog/seo-local', false);
    }

    public function test_las_metas_caben_en_la_serp(): void
    {
        foreach (CiudadesSeo::todos() as $ciudad) {
            $this->assertLessThanOrEqual(60, mb_strlen($ciudad['meta_title']));
            $this->assertLessThanOrEqual(155, mb_strlen($ciudad['meta_description']));
        }
    }
}
