<?php

namespace Tests\Feature\Admin;

use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exportes que descarga el manejador `data-descarga`: con y sin datos deben
 * contestar 200 con un tipo de archivo válido (el JS rechaza HTML como error).
 */
class ExportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function conversion(Cliente $cliente, array $extra = []): AdsConversion
    {
        return AdsConversion::create($extra + [
            'cliente_id' => $cliente->id,
            'visitor_id' => 'v-'.uniqid(),
            'gclid' => 'gclid-prueba',
            'tipo' => 'whatsapp',
            'valor' => 100,
            'estado' => 'pendiente',
        ]);
    }

    // --- Dashboard y Finanzas -------------------------------------------------

    public function test_el_dashboard_exporta_pdf_con_la_base_vacia(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->get(route('admin.dashboard.exportar'));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('reporte-ejecutivo-', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertSame('%PDF', substr($respuesta->getContent(), 0, 4));
    }

    public function test_finanzas_vacio_exporta_un_csv_solo_con_encabezados(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->get(route('admin.finanzas.exportar'));

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Folio,Cliente,Concepto', $contenido);
        $this->assertCount(1, array_filter(preg_split('/\R/', $contenido)));
    }

    // --- Conversiones (listado general) ------------------------------------------

    public function test_exportar_conversiones_sin_datos_devuelve_csv_con_encabezados(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->post(route('admin.conversiones.exportar-excel'));

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Etapa del embudo', $respuesta->streamedContent());
    }

    public function test_exportar_conversiones_con_datos_incluye_las_filas(): void
    {
        $cliente = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Clinica Prueba']);
        $this->conversion($cliente);

        $contenido = $this->actingAs(User::factory()->create())
            ->post(route('admin.conversiones.exportar-excel'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Clinica Prueba', $contenido);
        $this->assertStringContainsString('gclid-prueba', $contenido);
    }

    // --- Integraciones (por cliente) ---------------------------------------------

    public function test_integraciones_exportar_csv_sin_pendientes_devuelve_csv_y_no_cambia_nada(): void
    {
        $cliente = Cliente::factory()->create();

        $respuesta = $this->actingAs(User::factory()->create())
            ->post(route('admin.clientes.conversiones.exportar', $cliente));

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('Google Click ID', $respuesta->streamedContent());
    }

    public function test_integraciones_exportar_csv_marca_las_pendientes_como_exportadas(): void
    {
        $cliente = Cliente::factory()->create();
        // Desde la deduplicación/clasificación solo se exporta lo que ya tiene etapa (y click id).
        $etapa = \App\Models\AdsEmbudoEtapa::create(['cliente_id' => $cliente->id, 'nombre' => 'Venta', 'orden' => 1]);
        $conversion = $this->conversion($cliente, ['ads_embudo_etapa_id' => $etapa->id]);

        $contenido = $this->actingAs(User::factory()->create())
            ->post(route('admin.clientes.conversiones.exportar', $cliente))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('gclid-prueba', $contenido);
        $this->assertSame('exportada', $conversion->fresh()->estado->value);
    }

    public function test_integraciones_exportar_con_etapa_con_y_sin_datos(): void
    {
        $cliente = Cliente::factory()->create();
        $usuario = User::factory()->create();

        $vacio = $this->actingAs($usuario)->post(route('admin.clientes.conversiones.exportar-excel', $cliente));
        $vacio->assertOk();
        $this->assertStringContainsString('text/csv', (string) $vacio->headers->get('Content-Type'));
        $this->assertStringContainsString('Etapa del embudo', $vacio->streamedContent());

        $this->conversion($cliente);
        $this->actingAs($usuario)
            ->post(route('admin.clientes.conversiones.exportar-excel', $cliente))
            ->assertOk();
    }

    // --- Marcado de los botones ----------------------------------------------------

    public function test_los_botones_de_exportar_usan_el_manejador_y_se_llaman_csv(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->actingAs($usuario)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('id="exportReportBtn"', false)
            ->assertSee('data-descarga', false);

        $this->actingAs($usuario)->get(route('admin.finanzas.index'))->assertOk()
            ->assertSee('id="finanzasExportBtn"', false)
            ->assertSee('data-descarga', false);

        $this->actingAs($usuario)->get(route('admin.conversiones.index'))->assertOk()
            ->assertSee('Exportar a CSV')
            ->assertDontSee('Exportar a Excel')
            ->assertSee('data-descarga', false);

        $this->actingAs($usuario)->get(route('admin.clientes.conversiones', $cliente))->assertOk()
            ->assertSee('Exportar a CSV (con etapa)')
            ->assertDontSee('Exportar a Excel')
            ->assertSee('data-descarga-confirmar=', false)
            ->assertSee('data-descarga-recargar', false);
    }
}
