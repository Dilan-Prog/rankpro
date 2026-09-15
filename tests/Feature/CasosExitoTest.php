<?php

namespace Tests\Feature;

use App\Support\CasosExito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La sección de casos de éxito de la landing de SEO publica datos reales de
 * clientes, así que lo que se prueba aquí son sobre todo las reglas
 * editoriales: a quién se nombra, qué cifras se imprimen y cuáles no.
 */
class CasosExitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_landing_de_seo_muestra_el_strip_y_la_seccion_de_casos(): void
    {
        $this->get('/servicios/seo-organico')
            ->assertOk()
            ->assertSee('Empresas que confían en RankPro')
            ->assertSee('Casos reales')
            ->assertSee('Lo que ha pasado en negocios como el tuyo');
    }

    public function test_solo_se_nombra_y_enlaza_a_los_clientes_con_consentimiento(): void
    {
        $html = $this->get('/servicios/seo-organico')->assertOk()->getContent();

        // Con consentimiento: nombre y enlace a su sitio.
        $this->assertStringContainsString('Hotel Fratelli', $html);
        $this->assertStringContainsString('href="https://hotelfratelli.com.mx/"', $html);
        $this->assertStringContainsString('Equiterm Industries', $html);
        $this->assertStringContainsString('href="https://equitermindustries.com.mx/"', $html);

        // Sin consentimiento: solo el sector. Ni nombre ni dominio, en ninguna forma.
        $this->assertStringNotContainsString('Mac del Norte', $html);
        $this->assertStringNotContainsStringIgnoringCase('macdelnorte', $html);
        $this->assertStringContainsString('Sector industrial B2B', $html);
    }

    public function test_los_enlaces_a_clientes_abren_en_pestana_nueva_con_noopener(): void
    {
        $html = $this->get('/servicios/seo-organico')->assertOk()->getContent();

        preg_match_all('/<a class="cv-caso__nombre"[^>]*>/', $html, $enlaces);

        $this->assertCount(2, $enlaces[0]);

        foreach ($enlaces[0] as $enlace) {
            $this->assertStringContainsString('target="_blank"', $enlace);
            $this->assertStringContainsString('rel="noopener"', $enlace);
        }
    }

    public function test_una_metrica_sin_dato_no_se_imprime(): void
    {
        // Los porcentajes de tráfico e ingresos existen en la estructura pero
        // aún no tienen cifra: la vista debe omitirlos, no pintar un hueco.
        $html = $this->get('/servicios/seo-organico')->assertOk()->getContent();

        $this->assertStringNotContainsString('de aumento en tráfico', $html);
        $this->assertStringNotContainsString('de aumento en ingresos', $html);

        // Y las que sí tienen dato, sí salen.
        $this->assertStringContainsString('10x', $html);
        $this->assertStringContainsString('$3 M MXN', $html);
    }

    public function test_el_strip_solo_anuncia_sectores_de_los_que_hay_caso(): void
    {
        $sectoresConCaso = array_unique(array_column(CasosExito::todos(), 'sector'));
        $anunciados = array_column(CasosExito::sectores(), 'label');

        $this->assertCount(count($sectoresConCaso), $anunciados);

        foreach ($sectoresConCaso as $clave) {
            $this->assertContains(CasosExito::SECTORES[$clave]['label'], $anunciados);
        }
    }

    public function test_el_catalogo_es_coherente(): void
    {
        foreach (CasosExito::todos() as $caso) {
            $this->assertArrayHasKey($caso['sector'], CasosExito::SECTORES, "Sector desconocido en {$caso['id']}");

            foreach ($caso['servicios'] as $servicio) {
                $this->assertArrayHasKey($servicio, CasosExito::SERVICIOS, "Servicio desconocido en {$caso['id']}");
            }

            // Nombre y url van juntos: nombrar sin enlazar o enlazar sin nombrar
            // dejaría el caso a medio identificar.
            $this->assertSame($caso['nombre'] === null, $caso['url'] === null, "Nombre y url deben ir juntos en {$caso['id']}");
        }
    }
}
