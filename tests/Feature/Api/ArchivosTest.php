<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Publico\ArchivoDescargaController;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // routes/api_publico_archivos.php (del Agente D) todavía no está
        // incluido en routes/api.php — lo registramos aquí solo para poder
        // probar de punta a punta ArchivosApiController::url(), que apunta
        // a esta ruta por nombre.
        if (! Route::has('api.publico.archivos.descargar')) {
            // 'api' además de 'signed': en producción esta ruta vive en
            // routes/api.php, que RouteServiceProvider siempre envuelve en el
            // grupo 'api' (trae SubstituteBindings, quien resuelve {archivo}
            // en un modelo real). Sin ese middleware aquí, el binding
            // implícito no corre y el controlador recibe un Archivo vacío.
            Route::get('/api/archivos/{archivo}/descargar', ArchivoDescargaController::class)
                ->middleware(['api', 'signed'])
                ->name('api.publico.archivos.descargar');

            // Route::name() solo toca $route->action['as']; el índice por
            // nombre de RouteCollection (lo que usa Route::has()/route()) se
            // arma una sola vez al terminar de cargar los archivos de rutas
            // (RouteServiceProvider::loadRoutesFrom), así que una ruta
            // registrada después de ese punto (como aquí) necesita este
            // refresh manual para ser encontrable por nombre.
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_index_filtra_por_cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'a.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'x/a.pdf', 'tamano' => 10, 'extension' => 'pdf', 'subido_por' => null]);
        Archivo::create(['cliente_id' => $otro->id, 'nombre' => 'b.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'x/b.pdf', 'tamano' => 10, 'extension' => 'pdf', 'subido_por' => null]);

        $this->getJson("/api/v1/archivos?cliente_id={$cliente->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_multipart_sube_archivo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/archivos', [
            'cliente_id' => $cliente->id,
            'tipo' => 'contrato',
            'archivo' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'contrato.pdf');
        $archivo = Archivo::first();
        Storage::disk('local')->assertExists($archivo->ruta_archivo);
    }

    public function test_store_base64_sube_archivo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/archivos', [
            'cliente_id' => $cliente->id,
            'tipo' => 'datos',
            'nombre' => 'datos.csv',
            'contenido_base64' => base64_encode("a,b,c\n1,2,3"),
        ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'datos.csv');
        $archivo = Archivo::first();
        Storage::disk('local')->assertExists($archivo->ruta_archivo);
        $this->assertSame("a,b,c\n1,2,3", Storage::disk('local')->get($archivo->ruta_archivo));
    }

    public function test_destroy_elimina_archivo_y_su_disco(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        Storage::disk('local')->put('x/borrar.pdf', 'contenido');
        $archivo = Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'borrar.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'x/borrar.pdf', 'tamano' => 9, 'extension' => 'pdf', 'subido_por' => null]);

        $this->deleteJson("/api/v1/archivos/{$archivo->id}")->assertOk()->assertJsonPath('data.deleted', true);
        Storage::disk('local')->assertMissing('x/borrar.pdf');
    }

    public function test_url_genera_url_firmada_que_descarga_el_archivo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        Storage::disk('local')->put('x/descargable.pdf', 'contenido real');
        $archivo = Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'descargable.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'x/descargable.pdf', 'tamano' => 14, 'extension' => 'pdf', 'subido_por' => null]);

        $response = $this->getJson("/api/v1/archivos/{$archivo->id}/url")->assertOk();
        $url = $response->json('data.url');
        $this->assertNotEmpty($url);

        // La URL firmada funciona SIN el header Authorization (fuera de auth:sanctum).
        $this->get($url)->assertOk();
    }

    public function test_escritura_prohibida_con_habilidad_de_solo_lectura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['archivos:leer']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/v1/archivos', [
            'cliente_id' => $cliente->id,
            'tipo' => 'contrato',
            'archivo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }
}
