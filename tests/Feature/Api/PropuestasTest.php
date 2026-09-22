<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoPropuesta;
use App\Models\Cliente;
use App\Models\Propuesta;
use App\Models\User;
use App\Support\Propuestas\Plantilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropuestasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function propuesta(array $overrides = []): Propuesta
    {
        return Propuesta::create(array_merge(Plantilla::vacio(), [
            'cliente_id' => Cliente::factory()->create()->id,
            'titulo' => 'Propuesta API',
            'estado' => EstadoPropuesta::Borrador->value,
        ], $overrides));
    }

    public function test_index_requiere_token(): void
    {
        $this->getJson('/api/v1/propuestas')->assertUnauthorized();
    }

    public function test_index_lista_propuestas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->propuesta();
        $this->propuesta();

        $this->getJson('/api/v1/propuestas')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_crea_propuesta_con_plantilla_vacia(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['propuestas:escribir']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/propuestas', ['cliente_id' => $cliente->id]);

        $response->assertCreated()->assertJsonPath('data.estado', EstadoPropuesta::Borrador->value);
        $this->assertDatabaseHas('propuestas', ['cliente_id' => $cliente->id]);
    }

    public function test_store_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['propuestas:leer']);

        $this->postJson('/api/v1/propuestas', ['cliente_id' => Cliente::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_estado_cambia_el_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $propuesta = $this->propuesta();

        $this->postJson("/api/v1/propuestas/{$propuesta->id}/estado", ['estado' => EstadoPropuesta::Enviada->value])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPropuesta::Enviada->value);
    }

    public function test_estado_rechaza_un_valor_fuera_del_enum(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $propuesta = $this->propuesta();

        $this->postJson("/api/v1/propuestas/{$propuesta->id}/estado", ['estado' => 'no-existe'])
            ->assertStatus(422);
    }

    public function test_secciones_actualizar_guarda_la_columna_resumen(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $propuesta = $this->propuesta();

        $this->patchJson("/api/v1/propuestas/{$propuesta->id}/secciones/resumen", [
            'titulo' => 'Propuesta editada',
            'estadisticas_destacadas' => [
                ['etiqueta' => 'Impresiones', 'valor' => '10,000', 'nota' => ''],
                ['etiqueta' => 'Clics', 'valor' => '500', 'nota' => ''],
            ],
        ])->assertOk()->assertJsonPath('data.titulo', 'Propuesta editada');

        $this->assertSame('Propuesta editada', $propuesta->fresh()->titulo);
    }

    public function test_pdf_devuelve_url_firmada_de_descarga(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $propuesta = $this->propuesta();

        $response = $this->postJson("/api/v1/propuestas/{$propuesta->id}/pdf")->assertCreated();

        $response->assertJsonStructure(['data' => ['archivo_id', 'url']]);
        $this->assertStringContainsString('signature=', $response->json('data.url'));
        $this->assertNotNull($propuesta->fresh()->folio);
    }

    public function test_destroy_elimina_propuesta(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $propuesta = $this->propuesta();

        $this->deleteJson("/api/v1/propuestas/{$propuesta->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('propuestas', ['id' => $propuesta->id]);
    }
}
