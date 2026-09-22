<?php

namespace Tests\Feature\Api;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\User;
use App\Support\Reportes\Plantillas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte mensual API',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
        ], $overrides);
    }

    private function reporte(array $overrides = []): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de prueba',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    public function test_index_requiere_token(): void
    {
        $this->getJson('/api/v1/reportes')->assertUnauthorized();
    }

    public function test_index_requiere_habilidad_de_modulo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['clientes:leer']);

        $this->getJson('/api/v1/reportes')->assertForbidden();
    }

    public function test_index_lista_reportes(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->reporte();
        $this->reporte();

        $this->getJson('/api/v1/reportes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_siembra_la_plantilla_del_area(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['reportes:escribir']);

        $response = $this->postJson('/api/v1/reportes', $this->payload());

        $response->assertCreated()->assertJsonPath('data.estado', EstadoReporte::Borrador->value);

        $reporte = Reporte::latest('id')->first();
        $this->assertCount(count(Plantillas::para(AreaReporte::Seo)), $reporte->secciones);
    }

    public function test_store_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['reportes:leer']);

        $this->postJson('/api/v1/reportes', $this->payload())->assertForbidden();
    }

    public function test_show_incluye_secciones_y_entregas_bajo_demanda(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $sinIncluir = $this->getJson("/api/v1/reportes/{$reporte->id}")->assertOk();
        $this->assertArrayNotHasKey('secciones', $sinIncluir->json('data'));

        $conIncluir = $this->getJson("/api/v1/reportes/{$reporte->id}?incluir=secciones,entregas")->assertOk();
        $this->assertArrayHasKey('secciones', $conIncluir->json('data'));
        $this->assertArrayHasKey('entregas', $conIncluir->json('data'));
    }

    public function test_update_cambia_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $this->putJson("/api/v1/reportes/{$reporte->id}", [
            'titulo' => $reporte->titulo,
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Listo->value,
        ])->assertOk()->assertJsonPath('data.estado', EstadoReporte::Listo->value);
    }

    public function test_destroy_elimina_reporte(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $this->deleteJson("/api/v1/reportes/{$reporte->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('reportes', ['id' => $reporte->id]);
    }

    // --- secciones -------------------------------------------------------

    public function test_secciones_store_update_reordenar_destroy(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $store = $this->postJson("/api/v1/reportes/{$reporte->id}/secciones", [
            'tipo' => 'kpis',
            'titulo' => 'Sección nueva',
        ])->assertCreated();

        $seccionId = $store->json('data.id');
        $this->assertNotNull($seccionId);

        $this->putJson("/api/v1/reportes/{$reporte->id}/secciones/{$seccionId}", [
            'titulo' => 'Sección editada',
            'visible' => true,
            'contenido' => $store->json('data.contenido'),
        ])->assertOk()->assertJsonPath('data.titulo', 'Sección editada');

        $ids = $reporte->fresh()->secciones()->pluck('id')->reverse()->values()->all();
        $this->postJson("/api/v1/reportes/{$reporte->id}/secciones/reordenar", ['orden' => $ids])
            ->assertOk();

        $this->deleteJson("/api/v1/reportes/{$reporte->id}/secciones/{$seccionId}")
            ->assertOk()->assertJsonPath('data.deleted', true);
    }

    public function test_seccion_de_otro_reporte_da_404(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporteA = $this->reporte();
        $reporteB = $this->reporte();
        $seccionDeB = $reporteB->secciones()->create([
            'tipo' => 'kpis', 'titulo' => 'De B', 'orden' => 1, 'contenido' => [], 'visible' => true,
        ]);

        $this->putJson("/api/v1/reportes/{$reporteA->id}/secciones/{$seccionDeB->id}", [
            'titulo' => 'Hackeo',
            'contenido' => [],
        ])->assertNotFound();
    }

    // --- generación (pdf/xlsx) --------------------------------------------

    public function test_pdf_devuelve_url_firmada_de_descarga(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $response = $this->postJson("/api/v1/reportes/{$reporte->id}/pdf")->assertCreated();

        $response->assertJsonStructure(['data' => ['archivo_id', 'url']]);
        $this->assertStringContainsString('signature=', $response->json('data.url'));
        $this->assertSame(EstadoReporte::Listo->value, $reporte->fresh()->estado->value);
    }

    public function test_xlsx_devuelve_url_firmada_de_descarga(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $reporte = $this->reporte();

        $response = $this->postJson("/api/v1/reportes/{$reporte->id}/xlsx")->assertCreated();

        $response->assertJsonStructure(['data' => ['archivo_id', 'url']]);
    }

    public function test_pdf_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['reportes:leer']);
        $reporte = $this->reporte();

        $this->postJson("/api/v1/reportes/{$reporte->id}/pdf")->assertForbidden();
    }
}
