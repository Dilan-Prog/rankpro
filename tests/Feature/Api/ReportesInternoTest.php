<?php

namespace Tests\Feature\Api;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Campo `interno` de las secciones de reporte en la API v1. */
class ReportesInternoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
    }

    private function reporte(): Reporte
    {
        return Reporte::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte API',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ]);
    }

    public function test_una_seccion_nueva_devuelve_interno_false(): void
    {
        $reporte = $this->reporte();

        $this->postJson("/api/v1/reportes/{$reporte->id}/secciones", ['tipo' => 'kpis', 'titulo' => 'KPIs'])
            ->assertCreated()
            ->assertJsonPath('data.interno', false);
    }

    public function test_update_acepta_y_devuelve_interno_y_el_index_lo_lista(): void
    {
        $reporte = $this->reporte();
        $store = $this->postJson("/api/v1/reportes/{$reporte->id}/secciones", ['tipo' => 'kpis', 'titulo' => 'KPIs'])->assertCreated();
        $id = $store->json('data.id');

        $this->putJson("/api/v1/reportes/{$reporte->id}/secciones/{$id}", [
            'titulo' => 'KPIs',
            'interno' => true,
            'contenido' => $store->json('data.contenido'),
        ])->assertOk()->assertJsonPath('data.interno', true);

        $this->getJson("/api/v1/reportes/{$reporte->id}/secciones")
            ->assertOk()
            ->assertJsonPath('data.0.interno', true);
    }

    public function test_update_rechaza_un_interno_que_no_es_booleano(): void
    {
        $reporte = $this->reporte();
        $store = $this->postJson("/api/v1/reportes/{$reporte->id}/secciones", ['tipo' => 'kpis', 'titulo' => 'KPIs'])->assertCreated();

        $this->putJson("/api/v1/reportes/{$reporte->id}/secciones/".$store->json('data.id'), [
            'titulo' => 'KPIs',
            'interno' => 'tal vez',
            'contenido' => $store->json('data.contenido'),
        ])->assertStatus(422);
    }

    public function test_show_con_secciones_incluye_interno(): void
    {
        $reporte = $this->reporte();
        $reporte->secciones()->create([
            'tipo' => 'kpis', 'titulo' => 'Pagos', 'orden' => 1, 'contenido' => [], 'visible' => true, 'interno' => true,
        ]);

        $this->getJson("/api/v1/reportes/{$reporte->id}?incluir=secciones")
            ->assertOk()
            ->assertJsonPath('data.secciones.0.interno', true);
    }

    public function test_el_pdf_por_api_no_incluye_secciones_internas(): void
    {
        $reporte = $this->reporte();
        $reporte->secciones()->create([
            'tipo' => 'texto', 'titulo' => 'Pagos', 'orden' => 1, 'visible' => true, 'interno' => true,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Tarjeta', 'cuerpo' => 'SALDO-SECRETO']]],
        ]);

        $vistas = [];
        view()->creator('pdf.reporte', function ($vista) use (&$vistas) {
            $vistas[] = $vista->getData();
        });

        $this->postJson("/api/v1/reportes/{$reporte->id}/pdf")->assertCreated();

        $this->assertNotEmpty($vistas);
        $this->assertSame([], $vistas[0]['secciones']);
    }
}
