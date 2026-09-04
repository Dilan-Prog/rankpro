<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KeywordsTest extends TestCase
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

    // --- store ---------------------------------------------------------

    public function test_admin_can_create_keyword(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'dentista cdmx',
            'tipo' => 'principal',
            'volumen_busqueda' => 8100,
            'dificultad' => 42,
            'cpc_estimado' => 45.20,
            'intencion' => 'transaccional',
            'estado' => 'en_uso',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'keyword', 'cliente_id', 'cliente', 'lista_id', 'lista_nombre',
            'tipo', 'volumen_busqueda', 'dificultad', 'cpc_estimado', 'intencion',
            'url_asignada', 'posicion_actual', 'posicion_anterior', 'estado',
            'herramienta_origen', 'fecha_incorporacion', 'notas',
        ]);
        $response->assertJson([
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'lista_nombre' => $lista->nombre,
            'keyword' => 'dentista cdmx',
            'posicion_anterior' => null,
        ]);

        $this->assertDatabaseHas('keywords', [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'dentista cdmx',
            'tipo' => 'principal',
        ]);
    }

    public function test_store_requires_keyword(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('keyword');
    }

    public function test_store_rejects_invalid_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword invalida',
            'tipo' => 'invalido',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_rejects_invalid_estado(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword invalida',
            'tipo' => 'principal',
            'estado' => 'invalido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
    }

    public function test_store_rejects_lista_id_belonging_to_different_cliente(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create();
        $clienteB = Cliente::factory()->create();
        $listaDeB = $this->lista($clienteB);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $clienteA->id,
            'lista_id' => $listaDeB->id,
            'keyword' => 'keyword cruzada',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('lista_id');
    }

    public function test_store_accepts_lista_id_belonging_to_same_cliente(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'keyword correcta',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response->assertCreated();
        $response->assertJson(['lista_id' => $lista->id]);
        $this->assertDatabaseHas('keywords', [
            'keyword' => 'keyword correcta',
            'lista_id' => $lista->id,
        ]);
    }

    // --- update ---------------------------------------------------------

    public function test_update_changes_fields(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create([
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword original',
            'tipo' => 'secundaria',
            'estado' => 'seguimiento',
        ]);

        $response = $this->actingAs($user)->putJson(route('admin.keywords.update', $keyword), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword actualizada',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'notas' => 'Nota actualizada',
        ]);

        $response->assertOk();
        $response->assertJson([
            'keyword' => 'keyword actualizada',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'notas' => 'Nota actualizada',
        ]);

        $this->assertDatabaseHas('keywords', [
            'id' => $keyword->id,
            'keyword' => 'keyword actualizada',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'notas' => 'Nota actualizada',
        ]);
    }

    /**
     * `posicion_anterior` cambio de significado al aparecer el historico
     * (keyword_mediciones): ya no es "el valor de antes de tu ultima edicion",
     * sino "la posicion en la ronda de medicion anterior". Las dos columnas del
     * banco son ahora una cache de las dos ultimas mediciones por fecha.
     *
     * Por eso dos ediciones el mismo dia NO producen delta: son correcciones de
     * la misma medicion, no dos mediciones. Es el comportamiento que quiere un
     * reporte mensual, donde "#5 (-5)" debe leerse "cinco puestos mejor que el
     * mes pasado" y no "cinco puestos mejor que hace tres minutos".
     */
    public function test_posicion_anterior_comes_from_the_previous_measurement_round(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        Carbon::setTestNow('2026-07-15 10:00:00');

        $crear = $this->actingAs($user)->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword posicion',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'posicion_actual' => 10,
        ]);

        $crear->assertCreated();
        $keyword = Keyword::firstWhere('keyword', 'keyword posicion');

        // Nacer con posicion crea ya su primera medicion: sin ella, el primer
        // avance que se midiera no tendria contra que compararse.
        $this->assertSame(10, $keyword->posicion_actual);
        $this->assertNull($keyword->posicion_anterior);
        $this->assertDatabaseHas('keyword_mediciones', [
            'keyword_id' => $keyword->id,
            'fecha' => '2026-07-15',
            'posicion' => 10,
        ]);

        // Correccion el mismo dia: sigue siendo una sola medicion, sin delta.
        $this->actingAs($user)->putJson(route('admin.keywords.update', $keyword), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword posicion',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'posicion_actual' => 9,
        ])->assertOk();

        $fresh = $keyword->fresh();
        $this->assertSame(9, $fresh->posicion_actual);
        $this->assertNull($fresh->posicion_anterior);
        $this->assertSame(1, KeywordMedicion::where('keyword_id', $keyword->id)->count());

        // Ronda del mes siguiente: ahora si hay contra que comparar.
        Carbon::setTestNow('2026-08-15 10:00:00');

        $this->actingAs($user)->putJson(route('admin.keywords.update', $keyword), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword posicion',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'posicion_actual' => 4,
        ])->assertOk();

        $siguiente = $keyword->fresh();
        $this->assertSame(4, $siguiente->posicion_actual);
        $this->assertSame(9, $siguiente->posicion_anterior);
        $this->assertSame(2, KeywordMedicion::where('keyword_id', $keyword->id)->count());

        // Reenviar la misma posicion no inventa una medicion nueva.
        $this->actingAs($user)->putJson(route('admin.keywords.update', $keyword), [
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword posicion',
            'tipo' => 'principal',
            'estado' => 'en_uso',
            'posicion_actual' => 4,
        ])->assertOk();

        $igual = $keyword->fresh();
        $this->assertSame(4, $igual->posicion_actual);
        $this->assertSame(9, $igual->posicion_anterior);
        $this->assertSame(2, KeywordMedicion::where('keyword_id', $keyword->id)->count());

        Carbon::setTestNow();
    }

    // --- destroy ---------------------------------------------------------

    public function test_destroy_hard_deletes_keyword(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create([
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword a borrar',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('admin.keywords.destroy', $keyword));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('keywords', ['id' => $keyword->id]);
    }

    // --- routes regression -----------------------------------------------

    public function test_old_create_edit_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('admin.keywords.create'));
        $this->assertFalse(Route::has('admin.keywords.edit'));
    }

    // --- auth ---------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.keywords.store'), [
            'cliente_id' => $cliente->id,
            'keyword' => 'sin autenticar',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create([
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword existente',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response = $this->putJson(route('admin.keywords.update', $keyword), [
            'cliente_id' => $cliente->id,
            'keyword' => 'intento sin autenticar',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $keyword = Keyword::create([
            'cliente_id' => $cliente->id,
            'keyword' => 'keyword existente',
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ]);

        $response = $this->deleteJson(route('admin.keywords.destroy', $keyword));

        $response->assertStatus(401);
    }
}
