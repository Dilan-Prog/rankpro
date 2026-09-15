<?php

namespace Tests\Feature;

use App\Support\CasosExito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los casos de éxito publican datos reales de clientes, así que lo que se
 * prueba aquí son sobre todo las reglas editoriales: a quién se nombra, qué
 * se imprime y, sobre todo, qué NO se imprime. El diseño de referencia traía
 * contenido inventado para rellenar la maqueta; estos tests son la barrera
 * para que nada de eso llegue al sitio.
 */
class CasosExitoTest extends TestCase
{
    use RefreshDatabase;

    /** Relleno verosímil de la maqueta que NUNCA debe aparecer publicado. */
    private const INVENTADO = [
        'Mac del Norte',
        'macdelnorte',
        'Fichas de habitación',
        'Google Business Profile',
        'Dirección general',
        'Fabricante industrial',
        'hotelfratelli.mx/',
    ];

    private function paginas(): array
    {
        return array_merge(
            ['/casos-de-exito', '/servicios/seo-organico'],
            array_map(fn ($c) => '/casos-de-exito/'.$c['slug'], CasosExito::todos()),
        );
    }

    public function test_el_listado_y_los_tres_detalles_responden(): void
    {
        foreach ($this->paginas() as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/casos-de-exito/no-existe')->assertNotFound();
    }

    public function test_la_landing_de_seo_muestra_el_strip_y_el_teaser_enlazando_a_los_casos(): void
    {
        $html = $this->get('/servicios/seo-organico')->assertOk()->getContent();

        $this->assertStringContainsString('Empresas que confían en RankPro', $html);
        $this->assertStringContainsString('Ver el caso completo', $html);

        foreach (CasosExito::todos() as $caso) {
            $this->assertStringContainsString(route('casos.show', $caso['slug']), $html);
        }
    }

    public function test_solo_se_nombra_y_enlaza_a_los_clientes_con_consentimiento(): void
    {
        foreach ($this->paginas() as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (self::INVENTADO as $prohibido) {
                $this->assertStringNotContainsStringIgnoringCase($prohibido, $html, "«{$prohibido}» aparece en {$url}");
            }
        }

        // Con consentimiento: nombre en el listado y enlace en su detalle.
        $listado = $this->get('/casos-de-exito')->getContent();
        $this->assertStringContainsString('Hotel Fratelli', $listado);
        $this->assertStringContainsString('Equiterm Industries', $listado);
        $this->assertStringContainsString('Industrial B2B', $listado);

        $fratelli = $this->get('/casos-de-exito/hotel-fratelli')->getContent();
        $this->assertStringContainsString('href="https://hotelfratelli.com.mx/"', $fratelli);

        $equiterm = $this->get('/casos-de-exito/equiterm-industries')->getContent();
        $this->assertStringContainsString('href="https://equitermindustries.com.mx/"', $equiterm);

        // Sin consentimiento: ni nombre, ni enlace saliente, ni cita.
        $anonimo = $this->get('/casos-de-exito/industrial-b2b')->getContent();
        $this->assertStringContainsString('Cliente anonimizado', $anonimo);
        $this->assertStringNotContainsString('rel="noopener"', $anonimo);
        $this->assertStringContainsString('Cómo verificamos este caso', $anonimo);
    }

    public function test_los_enlaces_a_clientes_abren_en_pestana_nueva_con_noopener(): void
    {
        foreach (['hotel-fratelli', 'equiterm-industries'] as $slug) {
            $html = $this->get('/casos-de-exito/'.$slug)->assertOk()->getContent();

            preg_match_all('/<a[^>]+href="https:\/\/(?:hotelfratelli|equitermindustries)\.com\.mx\/"[^>]*>/', $html, $enlaces);

            $this->assertNotEmpty($enlaces[0], "Sin enlace al cliente en {$slug}");

            foreach ($enlaces[0] as $enlace) {
                $this->assertStringContainsString('target="_blank"', $enlace);
                $this->assertStringContainsString('rel="noopener"', $enlace);
            }
        }
    }

    public function test_toda_cifra_lleva_su_fuente_y_los_bloques_vacios_se_omiten(): void
    {
        $html = $this->get('/casos-de-exito/industrial-b2b')->assertOk()->getContent();

        $this->assertStringContainsString('10x', $html);
        $this->assertStringContainsString('$3 M', $html);
        $this->assertStringContainsString('Google Search Console', $html);
        $this->assertStringContainsString('datos del cliente', $html);

        // La solución por servicio no está documentada por el cliente: la
        // sección no debe existir, y menos con tácticas de relleno.
        $this->assertStringNotContainsString('La solución', $html);
        $this->assertStringNotContainsString('Evolución', $html);
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
        $slugs = [];

        foreach (CasosExito::todos() as $caso) {
            $this->assertArrayHasKey($caso['sector'], CasosExito::SECTORES, "Sector desconocido en {$caso['slug']}");
            $this->assertNotContains($caso['slug'], $slugs, "Slug repetido: {$caso['slug']}");
            $slugs[] = $caso['slug'];

            foreach ($caso['servicios'] as $s) {
                $this->assertArrayHasKey($s, CasosExito::SERVICIOS, "Servicio desconocido en {$caso['slug']}");
            }

            foreach ($caso['kpis'] as $kpi) {
                $this->assertArrayHasKey($kpi['fuente'], CasosExito::FUENTES, "KPI sin fuente válida en {$caso['slug']}");
            }

            // Nombre y url van juntos: nombrar sin enlazar o enlazar sin nombrar
            // dejaría el caso a medio identificar.
            $this->assertSame($caso['nombre'] === null, $caso['url'] === null, "Nombre y url deben ir juntos en {$caso['slug']}");

            // La description del detalle sale del resumen: si pasa de 160, SeoTest
            // lo recorta con «…», que es feo. Mejor que no ocurra.
            $this->assertLessThanOrEqual(160, mb_strlen($caso['resumen']), "Resumen demasiado largo en {$caso['slug']}");
        }

        $this->assertSame(count(CasosExito::todos()), array_sum(CasosExito::conteos()['sectores']));
    }
}
