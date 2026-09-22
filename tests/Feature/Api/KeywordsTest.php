<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KeywordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requiere_habilidad_keywords(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['seo:leer']);

        $this->getJson('/api/v1/keywords')->assertStatus(403)
            ->assertJsonPath('habilidad_requerida', 'keywords:leer');
    }

    public function test_store_y_filtros_de_index(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/v1/keywords', [
            'cliente_id' => $cliente->id,
            'keyword' => 'agencia de marketing digital',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ])->assertCreated()->assertJsonPath('data.keyword', 'agencia de marketing digital');

        $this->getJson("/api/v1/keywords?cliente_id={$cliente->id}&estado=en_uso")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_store_con_posicion_crea_medicion_inicial(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $respuesta = $this->postJson('/api/v1/keywords', [
            'cliente_id' => $cliente->id,
            'keyword' => 'seo cdmx',
            'tipo' => 'principal',
            'estado' => 'seguimiento',
            'posicion_actual' => 12,
        ])->assertCreated();

        $keyword = Keyword::find($respuesta->json('data.id'));
        $this->assertEquals(1, $keyword->mediciones()->count());
        $this->assertEquals(12, $keyword->posicion_actual);
    }

    public function test_update_requiere_habilidad_de_escritura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['keywords:leer']);
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create([
            'cliente_id' => $cliente->id, 'keyword' => 'x', 'tipo' => 'principal', 'estado' => 'seguimiento',
        ]);

        $this->putJson("/api/v1/keywords/{$keyword->id}", [
            'cliente_id' => $cliente->id, 'keyword' => 'y', 'tipo' => 'principal', 'estado' => 'seguimiento',
        ])->assertStatus(403);
    }

    public function test_bulk_descartar_marca_keywords_no_listas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $k1 = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'a', 'tipo' => 'principal', 'estado' => 'en_uso']);
        $k2 = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'b', 'tipo' => 'principal', 'estado' => 'en_uso']);

        $this->postJson('/api/v1/keywords/bulk-descartar', ['ids' => [$k1->id, $k2->id]])
            ->assertOk();

        $this->assertEquals('descartada', $k1->fresh()->estado->value);
        $this->assertEquals('descartada', $k2->fresh()->estado->value);
    }

    public function test_destroy_keyword(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'x', 'tipo' => 'principal', 'estado' => 'seguimiento']);

        $this->deleteJson("/api/v1/keywords/{$keyword->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('keywords', ['id' => $keyword->id]);
    }

    public function test_listas_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $respuesta = $this->postJson('/api/v1/keywords/listas', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista principal',
            'estado' => 'en_uso',
        ])->assertCreated();

        $id = $respuesta->json('data.id');

        $this->putJson("/api/v1/keywords/listas/{$id}", [
            'cliente_id' => $cliente->id, 'nombre' => 'Lista renombrada', 'estado' => 'en_uso',
        ])->assertOk()->assertJsonPath('data.nombre', 'Lista renombrada');

        $this->deleteJson("/api/v1/keywords/listas/{$id}")->assertOk();
        $this->assertSoftDeleted('keyword_listas', ['id' => $id]);
    }

    public function test_importar_json_crea_filas_y_reporta_errores(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Importadas', 'estado' => 'en_uso']);

        $respuesta = $this->postJson("/api/v1/keywords/listas/{$lista->id}/importar", [
            'keywords' => [
                ['keyword' => 'seo local', 'volumen_busqueda' => 500],
                ['keyword' => ''], // fila inválida: keyword vacía
            ],
        ])->assertCreated();

        $respuesta->assertJsonPath('data.creadas', 1);
        $this->assertCount(1, $respuesta->json('data.errores'));
        $this->assertEquals(1, $lista->keywords()->count());
    }

    public function test_importar_saltar_duplicados(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Con duplicados', 'estado' => 'en_uso']);
        Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'seo local', 'tipo' => 'principal', 'estado' => 'seguimiento']);

        $respuesta = $this->postJson("/api/v1/keywords/listas/{$lista->id}/importar", [
            'modo' => 'saltar_duplicados',
            'keywords' => [
                ['keyword' => 'SEO Local'],
                ['keyword' => 'seo nueva'],
            ],
        ])->assertCreated();

        $respuesta->assertJsonPath('data.creadas', 1)->assertJsonPath('data.duplicadas', 1);
    }

    public function test_mediciones_lote_para_n8n(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'L', 'estado' => 'en_uso']);
        $keyword = Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'k', 'tipo' => 'principal', 'estado' => 'en_uso']);

        $this->postJson('/api/v1/keywords/mediciones/lote', [
            ['keyword_id' => $keyword->id, 'fecha' => '2026-01-15', 'posicion' => 8],
        ])->assertCreated()->assertJsonPath('data.procesadas', 1);

        $this->assertEquals(8, $keyword->fresh()->posicion_actual);
    }

    public function test_mediciones_lote_requiere_habilidad_de_escritura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['keywords:leer']);

        $this->postJson('/api/v1/keywords/mediciones/lote', [
            ['keyword_id' => 1, 'fecha' => '2026-01-15'],
        ])->assertStatus(403);
    }
}
