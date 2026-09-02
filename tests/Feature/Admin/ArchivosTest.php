<?php

namespace Tests\Feature\Admin;

use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the rewritten ArchivosController: index() is now a client
 * picker with real per-client KPIs, store() is a real multipart upload
 * endpoint (previously files only ever arrived via DocumentosController's
 * PDF generation — untouched, not covered here), and destroy() is an AJAX
 * JSON endpoint. Also covers Cliente's cascade-delete hook, which was
 * changed to delete the real files backing a client's Archivo rows from
 * the 'local' disk (previously it only removed the DB rows, orphaning
 * files) — that behavior lives on Cliente, not ArchivosController, but is
 * kept in this file since it's directly about file-storage cleanup.
 */
class ArchivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Hand-builds an Archivo backed by a real file on the (faked) 'local'
     * disk — no ArchivoFactory exists in database/factories/, so this
     * mirrors the hand-rolled-helper convention FinanzasTest's finanza()
     * and SeoCampanasTest's campana() establish for models without one.
     */
    private function archivoConArchivoReal(Cliente $cliente, array $overrides = []): Archivo
    {
        $contenido = $overrides['contenido'] ?? str_repeat('a', 2048);
        unset($overrides['contenido']);

        $path = $overrides['ruta_archivo'] ?? "clientes/{$cliente->id}/archivos/" . Str::random(20) . '.pdf';
        unset($overrides['ruta_archivo']);

        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, $contenido);
        }

        return Archivo::create(array_merge([
            'cliente_id' => $cliente->id,
            'nombre' => 'archivo-prueba.pdf',
            'tipo' => 'otro',
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => null,
        ], $overrides));
    }

    // --- index ---------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.archivos.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_ok_and_correct_view(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.archivos.index'));

        $response->assertOk();
        $response->assertViewIs('admin.archivos.index');
    }

    public function test_clientes_includes_clients_with_zero_archivos(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create(['nombre' => 'Cliente Sin Archivos']);

        $response = $this->actingAs($user)->get(route('admin.archivos.index'));

        $row = $response->viewData('clientes')->firstWhere('cliente_id', $cliente->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['archivos_count']);
        $this->assertSame(0.0, $row['peso_mb']);
    }

    public function test_client_picker_stats_do_not_leak_between_clients(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create(['nombre' => 'Cliente A']);
        $clienteB = Cliente::factory()->create(['nombre' => 'Cliente B']);

        $this->archivoConArchivoReal($clienteA, ['contenido' => str_repeat('a', 1048576)]);
        $this->archivoConArchivoReal($clienteA, ['contenido' => str_repeat('a', 1048576)]);
        $this->archivoConArchivoReal($clienteB, ['contenido' => str_repeat('a', 2097152)]);

        $response = $this->actingAs($user)->get(route('admin.archivos.index'));

        $rowA = $response->viewData('clientes')->firstWhere('cliente_id', $clienteA->id);
        $rowB = $response->viewData('clientes')->firstWhere('cliente_id', $clienteB->id);

        $this->assertSame(2, $rowA['archivos_count']);
        $this->assertSame(2.0, $rowA['peso_mb']);
        $this->assertSame(1, $rowB['archivos_count']);
        $this->assertSame(2.0, $rowB['peso_mb']);
    }

    public function test_cliente_query_param_selects_that_client(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nombre' => 'AAA Cliente']);
        $clienteB = Cliente::factory()->create(['nombre' => 'ZZZ Cliente']);

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $clienteB->id]));

        $response->assertViewHas('clienteSeleccionado', $clienteB->id);
    }

    public function test_omitting_cliente_param_defaults_to_alphabetically_first_client(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create(['nombre' => 'Zeta Cliente']);
        $clienteA = Cliente::factory()->create(['nombre' => 'Alfa Cliente']);

        $response = $this->actingAs($user)->get(route('admin.archivos.index'));

        $response->assertViewHas('clienteSeleccionado', $clienteA->id);
    }

    public function test_archivos_for_selected_client_are_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $antiguo = $this->archivoConArchivoReal($cliente, ['nombre' => 'Antiguo.pdf']);
        $antiguo->forceFill(['created_at' => now()->subDays(5)])->save();

        $reciente = $this->archivoConArchivoReal($cliente, ['nombre' => 'Reciente.pdf']);
        $reciente->forceFill(['created_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $cliente->id]));

        $archivos = $response->viewData('archivos');

        $this->assertSame($reciente->id, $archivos->first()['id']);
        $this->assertSame($antiguo->id, $archivos->last()['id']);
    }

    public function test_archivo_row_shape_and_labels(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente, [
            'tipo' => 'datos',
            'nombre' => 'Datos.csv',
            'extension' => 'csv',
            'contenido' => str_repeat('a', 1048576),
        ]);

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $cliente->id]));

        $row = $response->viewData('archivos')->firstWhere('id', $archivo->id);

        $this->assertSame([
            'id', 'cliente_id', 'nombre', 'tipo', 'tipo_label', 'extension',
            'tamano', 'tamano_label', 'subido_por', 'fecha', 'download_url',
        ], array_keys($row));

        $this->assertSame('datos', $row['tipo']);
        $this->assertSame('Datos', $row['tipo_label']);
        $this->assertSame('1.0 MB', $row['tamano_label']);
        $this->assertSame(route('admin.archivos.download', $archivo->id), $row['download_url']);
    }

    public function test_categorias_contains_all_seven_tipo_archivo_cases(): void
    {
        $user = User::factory()->create();
        Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.archivos.index'));

        $categorias = $response->viewData('categorias');

        $this->assertCount(7, $categorias);
        $this->assertEquals([
            ['value' => 'contrato', 'label' => 'Contrato'],
            ['value' => 'propuesta', 'label' => 'Propuesta'],
            ['value' => 'diseno', 'label' => 'Diseño'],
            ['value' => 'reporte', 'label' => 'Reporte'],
            ['value' => 'datos', 'label' => 'Datos'],
            ['value' => 'entregable', 'label' => 'Entregable'],
            ['value' => 'otro', 'label' => 'Otro'],
        ], $categorias->toArray());
    }

    public function test_kpis_are_scoped_to_selected_client_only(): void
    {
        $user = User::factory()->create();
        $clienteA = Cliente::factory()->create(['nombre' => 'Cliente A KPI']);
        $clienteB = Cliente::factory()->create(['nombre' => 'Cliente B KPI']);

        $this->archivoConArchivoReal($clienteA, ['tipo' => 'contrato', 'contenido' => str_repeat('a', 1048576)]);
        $this->archivoConArchivoReal($clienteB, ['tipo' => 'contrato', 'contenido' => str_repeat('a', 5242880)]);

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $clienteA->id]));

        $this->assertSame(1, $response->viewData('archivosCount'));
        $this->assertSame(1.0, $response->viewData('pesoTotalMb'));
        $this->assertSame(1, $response->viewData('contratosCount'));
    }

    /**
     * Archivo::tipo is cast to the TipoArchivo enum, so ArchivosController's
     * contratosCount must compare against the enum case (or ->value) rather
     * than a plain string — a sibling module in this same session
     * (SeoController's fase filtering) had a real bug where comparing a
     * cast-enum column against a plain string via Collection::filter/where
     * silently matched nothing, undercounting to 0. This seeds a mix of
     * types and asserts the count is exactly the number of 'contrato' rows,
     * which would fail (return 0) if that same bug were present here.
     */
    public function test_contratos_count_only_counts_contrato_type(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $this->archivoConArchivoReal($cliente, ['tipo' => 'contrato']);
        $this->archivoConArchivoReal($cliente, ['tipo' => 'contrato']);
        $this->archivoConArchivoReal($cliente, ['tipo' => 'propuesta']);
        $this->archivoConArchivoReal($cliente, ['tipo' => 'otro']);

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $cliente->id]));

        $this->assertSame(2, $response->viewData('contratosCount'));
    }

    public function test_ultimo_movimiento_is_most_recent_created_at_not_insertion_order(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $masReciente = $this->archivoConArchivoReal($cliente);
        $masReciente->forceFill(['created_at' => now()->subDay()])->save();

        // Inserted second but dated further in the past — must NOT win.
        $masAntiguo = $this->archivoConArchivoReal($cliente);
        $masAntiguo->forceFill(['created_at' => now()->subDays(10)])->save();

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $cliente->id]));

        $this->assertSame($masReciente->created_at->format('Y-m-d'), $response->viewData('ultimoMovimiento'));
    }

    public function test_zero_files_client_shows_zeroed_kpis_and_null_ultimo_movimiento(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.archivos.index', ['cliente' => $cliente->id]));

        $this->assertSame(0, $response->viewData('archivosCount'));
        $this->assertSame(0.0, $response->viewData('pesoTotalMb'));
        $this->assertSame(0, $response->viewData('contratosCount'));
        $this->assertNull($response->viewData('ultimoMovimiento'));
    }

    // --- store -----------------------------------------------------------

    public function test_store_requires_archivo_file(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    public function test_store_rejects_invalid_tipo(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'invalid_type',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', 'contenido'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tipo');
    }

    public function test_store_rejects_disallowed_mime_type(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    public function test_store_rejects_file_exceeding_100mb_limit(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            // 102401 KB > the 102400 KB (100 MB) cap. ->create() only sets
            // size metadata for validation — it never writes 100 MB of real
            // bytes, so this stays fast.
            'archivo' => UploadedFile::fake()->create('grande.pdf', 102401, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    public function test_store_rejects_nonexistent_cliente_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => 999999,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', 'contenido'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
    }

    public function test_store_valid_minimal_payload_succeeds(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo-minimo.pdf', 'contenido de prueba'),
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'cliente_id', 'nombre', 'tipo', 'tipo_label', 'extension',
            'tamano', 'tamano_label', 'subido_por', 'fecha', 'download_url',
        ]);
    }

    public function test_store_defaults_nombre_to_original_filename_when_omitted(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'reporte',
            'archivo' => UploadedFile::fake()->createWithContent('reporte-original.pdf', 'contenido'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('archivos', [
            'id' => $response->json('id'),
            'nombre' => 'reporte-original.pdf',
        ]);
    }

    public function test_store_uses_provided_nombre_instead_of_filename(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'reporte',
            'nombre' => 'Nombre Personalizado.pdf',
            'archivo' => UploadedFile::fake()->createWithContent('nombre-original.pdf', 'contenido'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('archivos', [
            'id' => $response->json('id'),
            'nombre' => 'Nombre Personalizado.pdf',
        ]);
    }

    public function test_store_persists_file_to_local_disk_under_cliente_path(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', 'contenido real'),
        ]);

        $archivo = Archivo::findOrFail($response->json('id'));

        $this->assertStringStartsWith("clientes/{$cliente->id}/archivos/", $archivo->ruta_archivo);
        Storage::disk('local')->assertExists($archivo->ruta_archivo);
    }

    public function test_store_tamano_equals_real_stored_file_size(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $contenido = str_repeat('x', 3000);

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', $contenido),
        ]);

        $archivo = Archivo::findOrFail($response->json('id'));

        $this->assertSame(3000, $archivo->tamano);
        $this->assertSame(Storage::disk('local')->size($archivo->ruta_archivo), $archivo->tamano);
        $this->assertSame(3000, $response->json('tamano'));
    }

    public function test_store_extension_is_lowercase_without_leading_dot(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            // Explicit mimeType so validation doesn't depend on guessing
            // from the (deliberately uppercase) filename extension.
            'archivo' => UploadedFile::fake()->create('Documento.PDF', 10, 'application/pdf'),
        ]);

        $response->assertCreated();
        $this->assertSame('pdf', $response->json('extension'));
        $this->assertDatabaseHas('archivos', [
            'id' => $response->json('id'),
            'extension' => 'pdf',
        ]);
    }

    public function test_store_sets_subido_por_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', 'contenido'),
        ]);

        $archivo = Archivo::findOrFail($response->json('id'));

        $this->assertSame($user->id, $archivo->subido_por);
        $this->assertSame($user->name, $response->json('subido_por'));
    }

    public function test_store_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();

        $response = $this->postJson(route('admin.archivos.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'otro',
            'archivo' => UploadedFile::fake()->createWithContent('archivo.pdf', 'contenido'),
        ]);

        $response->assertStatus(401);
    }

    // --- destroy -----------------------------------------------------------

    public function test_destroy_returns_200_with_deleted_true(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        $response = $this->actingAs($user)->deleteJson(route('admin.archivos.destroy', $archivo));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
    }

    /**
     * Archivo has no SoftDeletes trait (confirmed by reading the model),
     * unlike SeoCampana/AdsCampana in this same admin panel — so destroy()
     * physically removes the row and assertDatabaseMissing is the correct
     * assertion here, not assertSoftDeleted.
     */
    public function test_destroy_removes_db_row(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        $this->actingAs($user)->deleteJson(route('admin.archivos.destroy', $archivo));

        $this->assertDatabaseMissing('archivos', ['id' => $archivo->id]);
    }

    public function test_destroy_deletes_real_file_from_disk(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        Storage::disk('local')->assertExists($archivo->ruta_archivo);

        $this->actingAs($user)->deleteJson(route('admin.archivos.destroy', $archivo));

        Storage::disk('local')->assertMissing($archivo->ruta_archivo);
    }

    public function test_destroy_requires_authentication(): void
    {
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        $response = $this->deleteJson(route('admin.archivos.destroy', $archivo));

        $response->assertStatus(401);
    }

    // --- download (unchanged from before the rewrite — light sanity only) --

    public function test_download_streams_file_when_it_exists_on_disk(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        $response = $this->actingAs($user)->get(route('admin.archivos.download', $archivo));

        $response->assertOk();
    }

    public function test_download_redirects_back_with_error_when_file_missing_from_disk(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::factory()->create();
        $archivo = Archivo::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'fantasma.pdf',
            'tipo' => 'otro',
            'ruta_archivo' => 'clientes/999/archivos/no-existe.pdf',
            'tamano' => null,
            'extension' => 'pdf',
            'subido_por' => null,
        ]);

        $response = $this->actingAs($user)->get(route('admin.archivos.download', $archivo));

        $response->assertRedirect();
        $response->assertSessionHasErrors('archivo');
    }

    // --- Cliente cascade delete -> real file cleanup ------------------------

    /**
     * Previously Cliente's cascade-delete hook only removed the Archivo DB
     * rows, orphaning the real files on the 'local' disk. It now deletes
     * each archivo one-by-one (see Cliente::booted()) so the underlying
     * file goes with it.
     */
    public function test_deleting_cliente_cascades_delete_of_archivo_row_and_real_file(): void
    {
        $cliente = Cliente::factory()->create();
        $archivo = $this->archivoConArchivoReal($cliente);

        Storage::disk('local')->assertExists($archivo->ruta_archivo);

        $cliente->delete();

        $this->assertDatabaseMissing('archivos', ['id' => $archivo->id]);
        Storage::disk('local')->assertMissing($archivo->ruta_archivo);
    }

    public function test_deleting_cliente_removes_all_of_their_archivo_files_from_disk(): void
    {
        $cliente = Cliente::factory()->create();
        $archivo1 = $this->archivoConArchivoReal($cliente);
        $archivo2 = $this->archivoConArchivoReal($cliente);

        $cliente->delete();

        $this->assertDatabaseMissing('archivos', ['id' => $archivo1->id]);
        $this->assertDatabaseMissing('archivos', ['id' => $archivo2->id]);
        Storage::disk('local')->assertMissing($archivo1->ruta_archivo);
        Storage::disk('local')->assertMissing($archivo2->ruta_archivo);
    }
}
