<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Support\Servicios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing de /servicios/seo-organico (S-01): keyword en las piezas que pesan,
 * FAQ visible igual al schema y ningún enlace a páginas sin publicar.
 */
class LandingSeoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/servicios/seo-organico';

    public function test_lleva_los_metadatos_y_encabezados_del_plan(): void
    {
        $this->get(self::URL)->assertOk()
            ->assertSee('<title>Agencia SEO en México: Posicionamiento Orgánico | RankPro</title>', false)
            ->assertSeeInOrder([
                'Agencia de servicios SEO',
                'Agencia SEO en México que sabe qué te',
                'Sabemos qué te está frenando en Google. Y cómo arreglarlo.',
                'Servicio de posicionamiento SEO: qué hace una agencia SEO por tu sitio',
                'Cómo trabaja nuestra agencia SEO',
                'Qué revisamos en tu sitio',
                'Resultados de nuestro servicio de SEO',
                'Agencia SEO para empresas en todo México',
                'Preguntas frecuentes sobre el servicio de SEO',
                'Empieza por saber dónde estás parado',
            ]);
    }

    public function test_el_schema_declara_el_servicio_y_las_mismas_faq_que_se_ven(): void
    {
        $html = $this->get(self::URL)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $nodos = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);

        $this->assertSame('Servicio de posicionamiento SEO', $nodos->firstWhere('@type', 'Service')['name']);
        $this->assertCount(1, $nodos->where('@type', 'Organization'));
        $this->assertCount(1, $nodos->where('@type', 'BreadcrumbList'));

        $preguntas = $nodos->firstWhere('@type', 'FAQPage')['mainEntity'];
        $this->assertCount(6, $preguntas);

        $visible = strip_tags(preg_replace('/<script.*?<\/script>/s', '', $html));
        foreach ($preguntas as $pregunta) {
            $this->assertStringContainsString(e($pregunta['name']), $visible);
            $this->assertStringContainsString(e($pregunta['acceptedAnswer']['text']), $visible);
        }
    }

    public function test_no_enlaza_guias_sin_publicar(): void
    {
        $respuesta = $this->get(self::URL)
            ->assertDontSee('Antes de contratar, conviene entender el SEO');

        foreach (array_keys(Servicios::todos()['seo-organico']['guias']) as $slug) {
            $respuesta->assertDontSee('/blog/'.$slug.'"', false);
        }
    }

    public function test_una_guia_del_plan_se_enlaza_al_publicarse_tambien_desde_la_faq(): void
    {
        $slug = 'cuanto-cuesta-el-posicionamiento-seo';
        Articulo::factory()->delCluster('seo-organico')->create(['slug' => $slug]);

        $html = $this->get(self::URL)
            ->assertSee('Antes de contratar, conviene entender el SEO')
            ->getContent();

        // Uno en la lista de guías y otro en la respuesta; sin tarjeta duplicada.
        $this->assertSame(2, substr_count($html, 'href="'.route('blog.show', $slug).'">¿Cuánto cuesta'));
        $this->assertSame(2, substr_count($html, route('blog.show', $slug).'"'));
    }
}
