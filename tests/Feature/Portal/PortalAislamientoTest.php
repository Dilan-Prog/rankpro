<?php

namespace Tests\Feature\Portal;

use App\Models\IdentidadMarca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Con el enlace de un cliente nunca se ve ni se descarga nada de otro, ni adivinando ids. */
class PortalAislamientoTest extends TestCase
{
    use PreparaPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPortal();
        RateLimiter::clear('portal|127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->cerrarPortal();
        parent::tearDown();
    }

    public function test_cada_enlace_solo_ve_los_datos_de_su_cliente(): void
    {
        $a = $this->cliente('Hotel Alfa');
        $b = $this->cliente('Hotel Beta');
        [, $tokenA] = $this->enlace($a);
        [, $tokenB] = $this->enlace($b);

        $this->reporte($a, 'entregado', ['pdf_cliente'], 'REPORTE-DE-ALFA');
        $this->reporte($b, 'entregado', ['pdf_cliente'], 'REPORTE-DE-BETA');
        $this->propuesta($a, 'enviada', ['titulo' => 'PROPUESTA-DE-ALFA']);
        $this->propuesta($b, 'enviada', ['titulo' => 'PROPUESTA-DE-BETA']);
        $this->metrica($a, 'METRICA-DE-ALFA', 10, 12);
        $this->metrica($b, 'METRICA-DE-BETA', 10, 12);

        $paginas = ['portal.inicio', 'portal.reportes', 'portal.propuestas', 'portal.metricas'];
        foreach ($paginas as $nombre) {
            $htmlA = $this->get($this->ruta($nombre, $tokenA))->assertOk()->getContent();
            $htmlB = $this->get($this->ruta($nombre, $tokenB))->assertOk()->getContent();

            $this->assertStringContainsString('Hotel Alfa', $htmlA);
            $this->assertStringContainsString('Hotel Beta', $htmlB);
            foreach (['BETA', 'Beta'] as $ajeno) {
                $this->assertStringNotContainsString($ajeno, $htmlA, "{$nombre} de A muestra algo de B");
            }
            foreach (['ALFA', 'Alfa'] as $ajeno) {
                $this->assertStringNotContainsString($ajeno, $htmlB, "{$nombre} de B muestra algo de A");
            }
        }

        $this->assertStringContainsString('REPORTE-DE-ALFA', $this->get($this->ruta('portal.reportes', $tokenA))->getContent());
        $this->assertStringContainsString('PROPUESTA-DE-BETA', $this->get($this->ruta('portal.propuestas', $tokenB))->getContent());
        $this->assertStringContainsString('METRICA-DE-ALFA', $this->get($this->ruta('portal.metricas', $tokenA))->getContent());
    }

    public function test_descargar_con_el_id_de_otro_cliente_da_404(): void
    {
        $a = $this->cliente('Hotel Alfa');
        $b = $this->cliente('Hotel Beta');
        [, $tokenA] = $this->enlace($a);
        [, $tokenB] = $this->enlace($b);
        $reporteB = $this->reporte($b, 'entregado', ['pdf_cliente', 'pdf']);
        $propuestaB = $this->propuesta($b);
        $reporteA = $this->reporte($a, 'entregado', ['pdf_cliente']);
        $propuestaA = $this->propuesta($a);

        // El dueño sí descarga; el otro, con el id exacto, recibe 404 (no 403: no se confirma que exista).
        foreach ($reporteB->entregas as $entrega) {
            $this->get($this->ruta('portal.reportes.descargar', $tokenB, ['entrega' => $entrega->id]))->assertOk();
            $this->get($this->ruta('portal.reportes.descargar', $tokenA, ['entrega' => $entrega->id]))->assertNotFound();
        }
        $this->get($this->ruta('portal.propuestas.descargar', $tokenB, ['propuesta' => $propuestaB->id]))->assertOk();
        $this->get($this->ruta('portal.propuestas.descargar', $tokenA, ['propuesta' => $propuestaB->id]))->assertNotFound();

        // Y barriendo ids: con el enlace de A jamás sale un byte de los archivos o propuestas de B.
        for ($id = 1; $id <= 40; $id++) {
            $r = $this->get($this->ruta('portal.reportes.descargar', $tokenA, ['entrega' => $id]));
            $this->assertStringNotContainsString($reporteB->numero, (string) $r->headers->get('Content-Disposition'));
            $this->assertStringNotContainsString($reporteB->numero, $r->isOk() ? $r->streamedContent() : '');
            $p = $this->get($this->ruta('portal.propuestas.descargar', $tokenA, ['propuesta' => $id]));
            $this->assertStringNotContainsString($propuestaB->folio, (string) $p->headers->get('Content-Disposition'));
        }
        $this->assertNotNull($reporteA);
        $this->assertNotNull($propuestaA);
    }

    public function test_una_entrega_cuyo_archivo_es_de_otro_cliente_no_se_descarga(): void
    {
        $a = $this->cliente('Hotel Alfa');
        $b = $this->cliente('Hotel Beta');
        [, $tokenA] = $this->enlace($a);
        $reporteA = $this->reporte($a, 'entregado', ['pdf_cliente']);
        $entrega = $reporteA->entregas()->firstOrFail();

        // Dato corrupto: la entrega es de A pero su archivo quedó registrado a nombre de B.
        $entrega->archivo->forceFill(['cliente_id' => $b->id])->save();

        $this->get($this->ruta('portal.reportes.descargar', $tokenA, ['entrega' => $entrega->id]))->assertNotFound();
        $this->assertStringNotContainsString($reporteA->numero, $this->get($this->ruta('portal.reportes', $tokenA))->getContent());
    }

    public function test_el_logo_y_el_color_son_los_del_cliente_del_enlace(): void
    {
        $a = $this->cliente('Hotel Alfa');
        $b = $this->cliente('Hotel Beta');
        Storage::disk('local')->put('clientes/'.$a->id.'/identidad/logo.png', 'LOGO-DE-ALFA');
        IdentidadMarca::create([
            'cliente_id' => $a->id, 'logo_ruta' => 'clientes/'.$a->id.'/identidad/logo.png', 'slogan' => 'Hospitalidad de verdad',
            'paleta' => [['nombre' => 'Primario', 'hex' => '#112233']],
        ]);
        [, $tokenA] = $this->enlace($a);
        [, $tokenB] = $this->enlace($b);

        $html = $this->get($this->ruta('portal.inicio', $tokenA))->assertOk()->getContent();
        $this->assertStringContainsString('--portal-primario: #112233', $html);
        $this->assertStringContainsString('Hospitalidad de verdad', $html);
        $this->assertStringContainsString($this->ruta('portal.logo', $tokenA), $html);
        $this->assertSame('LOGO-DE-ALFA', $this->get($this->ruta('portal.logo', $tokenA))->assertOk()->streamedContent());

        // B no tiene identidad: usa la de RankPro y su ruta de logo no sirve el de A.
        $htmlB = $this->get($this->ruta('portal.inicio', $tokenB))->assertOk()->getContent();
        $this->assertStringContainsString('--portal-primario: #0F9D6E', $htmlB);
        $this->assertStringNotContainsString('portal-cab__logo', $htmlB);
        $this->get($this->ruta('portal.logo', $tokenB))->assertNotFound();
    }

    public function test_un_color_malicioso_en_la_paleta_no_inyecta_css(): void
    {
        $a = $this->cliente('Hotel Alfa');
        IdentidadMarca::create(['cliente_id' => $a->id, 'paleta' => [['nombre' => 'X', 'hex' => 'red;}</style><script>alert(1)</script>']]]);
        [, $token] = $this->enlace($a);

        $html = $this->get($this->ruta('portal.inicio', $token))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('--portal-primario: #0F9D6E', $html);
    }
}
