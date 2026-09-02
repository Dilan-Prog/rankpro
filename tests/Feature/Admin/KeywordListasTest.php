<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordListasTest extends TestCase
{
    use RefreshDatabase;

    private function lista(Cliente $cliente, array $overrides = []): KeywordLista
    {
        return KeywordLista::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista de Prueba',
            'canal' => 'seo',
            'estado' => 'en_uso',
        ], $overrides));
    }

    private function keyword(Cliente $cliente, ?KeywordLista $lista = null, array $overrides = []): Keyword
    {
        return Keyword::create(array_merge([
            'cliente_id' => $cliente->id,
            'lista_id' => $lista?->id,
            'keyword' => 'keyword de prueba '.uniqid(),
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ], $overrides));
    }

    // --- store ---------------------------------------------------------

    public function test_admin_can_create_lista(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista SEO Local',
            'canal' => 'seo',
            'estado' => 'en_uso',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista SEO Local',
            'canal' => 'seo',
            'estado' => 'en_uso',
        ]);

        $this->assertDatabaseHas('keyword_listas', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista SEO Local',
        ]);
    }

    public function test_store_responsable_id_accepts_null(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista sin responsable',
            'estado' => 'en_uso',
            'responsable_id' => null,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('keyword_listas', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista sin responsable',
            'responsable_id' => null,
        ]);
    }

    public function test_store_responsable_id_accepts_valid_user(): void
    {
        $user = User::factory()->create();
        $responsable = User::factory()->create(['name' => 'Responsable de Prueba']);
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista con responsable',
            'estado' => 'en_uso',
            'responsable_id' => $responsable->id,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'responsable_id' => $responsable->id,
            'responsable_nombre' => 'Responsable de Prueba',
        ]);
    }

    public function test_store_rejects_invalid_responsable_id(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista responsable invalido',
            'estado' => 'en_uso',
            'responsable_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('responsable_id');
    }

    // --- update ---------------------------------------------------------

    public function test_admin_can_update_lista(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente, ['nombre' => 'Nombre original']);

        $response = $this->actingAs($user)->putJson(route('admin.keywords.listas.update', $lista), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Nombre actualizado',
            'canal' => 'ads',
            'estado' => 'seguimiento',
        ]);

        $response->assertOk();
        $response->assertJson([
            'nombre' => 'Nombre actualizado',
            'canal' => 'ads',
            'estado' => 'seguimiento',
        ]);

        $this->assertDatabaseHas('keyword_listas', [
            'id' => $lista->id,
            'nombre' => 'Nombre actualizado',
            'canal' => 'ads',
            'estado' => 'seguimiento',
        ]);
    }

    // --- destroy ---------------------------------------------------------

    public function test_admin_can_delete_lista(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->actingAs($user)->deleteJson(route('admin.keywords.listas.destroy', $lista));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertSoftDeleted('keyword_listas', ['id' => $lista->id]);
    }

    public function test_deleting_lista_detaches_but_does_not_delete_its_keywords(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $keywordUno = $this->keyword($cliente, $lista);
        $keywordDos = $this->keyword($cliente, $lista);

        $response = $this->actingAs($user)->deleteJson(route('admin.keywords.listas.destroy', $lista));

        $response->assertOk();
        $this->assertSoftDeleted('keyword_listas', ['id' => $lista->id]);

        $this->assertDatabaseHas('keywords', ['id' => $keywordUno->id]);
        $this->assertDatabaseHas('keywords', ['id' => $keywordDos->id]);

        $this->assertNull($keywordUno->fresh()->lista_id);
        $this->assertNull($keywordDos->fresh()->lista_id);
    }

    public function test_deleting_cliente_soft_deletes_listas_and_hard_deletes_keywords(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $keywordUno = $this->keyword($cliente, $lista);
        $keywordDos = $this->keyword($cliente, $lista);

        $cliente->delete();

        $this->assertSoftDeleted('keyword_listas', ['id' => $lista->id]);
        $this->assertDatabaseMissing('keywords', ['id' => $keywordUno->id]);
        $this->assertDatabaseMissing('keywords', ['id' => $keywordDos->id]);
    }

    // --- bulkDescartar ---------------------------------------------------

    public function test_bulk_descartar_marks_all_given_listas_as_descartada(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $listaUno = $this->lista($cliente, ['nombre' => 'Lista Uno', 'estado' => 'en_uso']);
        $listaDos = $this->lista($cliente, ['nombre' => 'Lista Dos', 'estado' => 'seguimiento']);
        $listaTres = $this->lista($cliente, ['nombre' => 'Lista Tres', 'estado' => 'en_uso']);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.bulk-descartar'), [
            'ids' => [$listaUno->id, $listaDos->id, $listaTres->id],
        ]);

        $response->assertOk();
        $response->assertJsonCount(3, 'listas');

        foreach ([$listaUno, $listaDos, $listaTres] as $lista) {
            $this->assertSame('descartada', $lista->fresh()->estado->value);
            $this->assertDatabaseHas('keyword_listas', [
                'id' => $lista->id,
                'estado' => 'descartada',
            ]);
        }
    }

    public function test_bulk_descartar_rejects_invalid_id(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.bulk-descartar'), [
            'ids' => [$lista->id, 999999],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids.1');
    }

    // --- auth ---------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.keywords.listas.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista sin autenticar',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->putJson(route('admin.keywords.listas.update', $lista), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Intento sin autenticar',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->deleteJson(route('admin.keywords.listas.destroy', $lista));

        $response->assertStatus(401);
    }
}
