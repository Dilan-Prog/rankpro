<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KeywordImportTest extends TestCase
{
    use RefreshDatabase;

    private function lista(Cliente $cliente, array $overrides = []): KeywordLista
    {
        return KeywordLista::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'Lista de Importacion',
            'canal' => 'seo',
            'estado' => 'en_uso',
        ], $overrides));
    }

    public function test_valid_pasted_texto_creates_all_rows(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $texto = implode("\n", [
            'dentista cdmx, 8100, 42, 45.20, transaccional, /dentista-cdmx',
            'ortodoncia invisible, 2200, 45, 38.00, informacional, /ortodoncia',
            'blanqueamiento dental precio, 720, 20, 12.50, navegacional, /blanqueamiento',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'texto' => $texto,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'creadas' => 3,
            'errores' => [],
        ]);

        $this->assertDatabaseHas('keywords', [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'dentista cdmx',
            'volumen_busqueda' => 8100,
            'dificultad' => 42,
            'url_asignada' => '/dentista-cdmx',
        ]);
        $this->assertDatabaseHas('keywords', [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'ortodoncia invisible',
            'volumen_busqueda' => 2200,
        ]);
        $this->assertDatabaseHas('keywords', [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'blanqueamiento dental precio',
            'volumen_busqueda' => 720,
        ]);

        $this->assertSame(3, Keyword::where('lista_id', $lista->id)->count());
    }

    public function test_mixed_valid_and_malformed_rows_only_creates_valid_ones(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $texto = implode("\n", [
            'keyword valida uno, 100, 10, 1.5, informacional, /url-uno',
            'keyword invalida dificultad, 100, 150, 1.5, informacional, /url-dos',
            'keyword valida dos, 200, 20, 2.5, transaccional, /url-tres',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'texto' => $texto,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('creadas', 2);
        $response->assertJsonCount(1, 'errores');
        $response->assertJsonPath('errores.0.fila', 2);
        $response->assertJsonPath('errores.0.texto', 'keyword invalida dificultad, 100, 150, 1.5, informacional, /url-dos');
        $this->assertNotEmpty($response->json('errores.0.errores'));

        $this->assertDatabaseHas('keywords', [
            'lista_id' => $lista->id,
            'keyword' => 'keyword valida uno',
        ]);
        $this->assertDatabaseHas('keywords', [
            'lista_id' => $lista->id,
            'keyword' => 'keyword valida dos',
        ]);
        $this->assertDatabaseMissing('keywords', [
            'lista_id' => $lista->id,
            'keyword' => 'keyword invalida dificultad',
        ]);

        $this->assertSame(2, Keyword::where('lista_id', $lista->id)->count());
    }

    public function test_all_malformed_rows_creates_nothing_and_returns_422(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $texto = implode("\n", [
            'keyword uno, 100, 999, 1.5, informacional, /url-uno',
            'keyword dos, 100, 999, 1.5, informacional, /url-dos',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'texto' => $texto,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('creadas', 0);
        $response->assertJsonCount(2, 'errores');

        $this->assertDatabaseMissing('keywords', ['keyword' => 'keyword uno']);
        $this->assertDatabaseMissing('keywords', ['keyword' => 'keyword dos']);
        $this->assertSame(0, Keyword::where('lista_id', $lista->id)->count());
    }

    public function test_empty_texto_and_no_file_returns_422_with_message_only(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'texto' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissing(['creadas']);
        $this->assertArrayNotHasKey('errores', $response->json());

        $this->assertSame(0, Keyword::where('lista_id', $lista->id)->count());
    }

    public function test_csv_file_upload_produces_same_result_as_pasted_text(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $archivo = UploadedFile::fake()->createWithContent(
            'keywords.csv',
            "keyword desde archivo, 100, 10, 1.5, informacional, /url\n"
        );

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'archivo' => $archivo,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'creadas' => 1,
            'errores' => [],
        ]);

        $this->assertDatabaseHas('keywords', [
            'cliente_id' => $cliente->id,
            'lista_id' => $lista->id,
            'keyword' => 'keyword desde archivo',
            'volumen_busqueda' => 100,
            'dificultad' => 10,
            'url_asignada' => '/url',
        ]);
    }

    public function test_import_always_forces_destination_lista_cliente_regardless_of_texto_content(): void
    {
        $user = User::factory()->create();
        $clienteDestino = Cliente::factory()->create();
        $otroCliente = Cliente::factory()->create();
        $lista = $this->lista($clienteDestino);

        // The import format has no columns for cliente_id/lista_id — nothing in
        // the pasted text can override the destination list/client.
        $texto = "keyword sin override, 100, 10, 1.5, informacional, /url";

        $response = $this->actingAs($user)->postJson(route('admin.keywords.listas.importar', $lista), [
            'texto' => $texto,
        ]);

        $response->assertCreated();

        $keyword = Keyword::where('keyword', 'keyword sin override')->first();
        $this->assertNotNull($keyword);
        $this->assertSame($clienteDestino->id, $keyword->cliente_id);
        $this->assertSame($lista->id, $keyword->lista_id);
        $this->assertNotSame($otroCliente->id, $keyword->cliente_id);
    }
}
