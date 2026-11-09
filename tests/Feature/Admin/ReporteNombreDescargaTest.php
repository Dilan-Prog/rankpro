<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\User;
use App\Support\Reportes\NombreEntregable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nombre de descarga de los entregables (`cliente_periodo_FOLIO-vN.ext`) en el panel,
 * en la URL firmada de la API y en el Archivo visible; la ruta de almacenamiento no cambia.
 */
class ReporteNombreDescargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 9, 1, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reporte(array $overrides = [], string $empresa = 'Hotel Fratelli Ñandú & Co'): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => Cliente::factory()->create(['empresa' => $empresa])->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de prueba',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    public function test_el_helper_quita_acentos_y_espacios_y_usa_un_mes(): void
    {
        $reporte = $this->reporte(['numero' => 'REP-SEO-2026-0001'], 'Café  Ñoño Árbol');

        $this->assertSame('cafe-nono-arbol_2026-08_REP-SEO-2026-0001-v3.pdf', NombreEntregable::para($reporte->load('cliente'), 3, 'pdf'));
    }

    public function test_si_el_periodo_cruza_meses_usa_inicio_a_fin(): void
    {
        $reporte = $this->reporte(['numero' => 'REP-ADS-2026-0002', 'periodo_inicio' => '2026-07-15', 'periodo_fin' => '2026-09-10'], 'Clínica Dental');

        $this->assertSame('clinica-dental_2026-07_a_2026-09_REP-ADS-2026-0002-v1.xlsx', NombreEntregable::para($reporte->load('cliente'), 1, 'xlsx'));
    }

    public function test_la_descarga_web_y_el_archivo_visible_llevan_el_nombre_nuevo_pero_la_ruta_no_cambia(): void
    {
        $reporte = $this->reporte();
        $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'Alcance', 'orden' => 1, 'contenido' => ['modo' => 'pares', 'pares' => [], 'parrafos' => []], 'visible' => true]);

        $respuesta = $this->actingAs(User::factory()->create())->post(route('admin.reportes.pdf', $reporte))->assertOk();

        $esperado = 'hotel-fratelli-nandu-co_2026-08_REP-SEO-2026-0001-v1.pdf';
        $this->assertStringContainsString($esperado, (string) $respuesta->headers->get('Content-Disposition'));

        $entrega = $reporte->entregas()->firstOrFail();
        $this->assertSame($esperado, $entrega->archivo->nombre);
        $this->assertStringEndsWith('/reportes/REP-SEO-2026-0001-v1.pdf', $entrega->archivo->ruta_archivo);
    }

    public function test_la_url_firmada_de_la_api_descarga_con_el_nombre_nuevo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $url = $this->postJson("/api/v1/reportes/{$reporte->id}/xlsx")->assertCreated()->json('data.url');
        $respuesta = $this->get($url)->assertOk();

        $this->assertStringContainsString('hotel-fratelli-nandu-co_2026-08_REP-SEO-2026-0001-v1.xlsx', (string) $respuesta->headers->get('Content-Disposition'));
    }
}
