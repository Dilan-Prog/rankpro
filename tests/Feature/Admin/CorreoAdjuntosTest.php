<?php

namespace Tests\Feature\Admin;

use App\Mail\CorreoPlantillaMail;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\CorreoAdjunto;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\User;
use App\Services\Correo\EnviadorCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests de adjuntos de un envío de correo: CorreoAdjuntosController
 * (store/desdeArchivo/disponibles/destroy/descargar) y el envío real de los
 * adjuntos al mandar el correo (EnviadorCorreo -> CorreoPlantillaMail).
 */
class CorreoAdjuntosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // --- helpers ---------------------------------------------------------

    private function envio(array $overrides = []): CorreoEnvio
    {
        return CorreoEnvio::factory()->create(array_merge(['plantilla_id' => CorreoPlantilla::factory()->create()->id], $overrides));
    }

    /** No existe ArchivoFactory: mismo patrón hand-rolled que ArchivosTest. */
    private function archivoConArchivoReal(array $overrides = []): Archivo
    {
        $cliente = $overrides['cliente'] ?? Cliente::factory()->create();
        unset($overrides['cliente']);

        $contenido = $overrides['contenido'] ?? str_repeat('a', 2048);
        unset($overrides['contenido']);

        $path = $overrides['ruta_archivo'] ?? 'clientes/'.$cliente->id.'/archivos/'.Str::random(20).'.pdf';
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

    // --- store -------------------------------------------------------------

    public function test_store_uploads_a_valid_attachment(): void
    {
        $envio = $this->envio();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            route('admin.correo.envios.adjuntos.store', $envio),
            ['archivo' => UploadedFile::fake()->create('Propuesta.pdf', 100, 'application/pdf')]
        );

        $response->assertCreated();
        $response->assertJsonPath('nombre', 'Propuesta.pdf');
        $response->assertJsonPath('extension', 'pdf');

        $adjunto = CorreoAdjunto::firstOrFail();
        $this->assertSame($envio->id, $adjunto->envio_id);
        $this->assertSame($user->id, $adjunto->subido_por);
        Storage::disk('local')->assertExists($adjunto->ruta);
    }

    public function test_store_rejects_disallowed_mime_type(): void
    {
        $envio = $this->envio();

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.adjuntos.store', $envio),
            ['archivo' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    public function test_store_rejects_a_sixth_attachment(): void
    {
        $envio = $this->envio();
        CorreoAdjunto::factory()->count(5)->create(['envio_id' => $envio->id]);

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.adjuntos.store', $envio),
            ['archivo' => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Un envío admite como máximo 5 adjuntos.');
    }

    public function test_store_rejects_when_envio_is_not_editable(): void
    {
        $envio = $this->envio(['estado' => 'enviado']);

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.adjuntos.store', $envio),
            ['archivo' => UploadedFile::fake()->create('archivo.pdf', 10, 'application/pdf')]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Este envío ya no admite cambios.');
    }

    // --- desdeArchivo --------------------------------------------------------

    public function test_desde_archivo_copies_the_file_without_touching_the_original(): void
    {
        $envio = $this->envio();
        $archivo = $this->archivoConArchivoReal();

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.adjuntos.desde-archivo', $envio),
            ['archivo_id' => $archivo->id]
        );

        $response->assertCreated();
        $response->assertJsonPath('nombre', $archivo->nombre);

        $adjunto = CorreoAdjunto::firstOrFail();
        $this->assertNotSame($archivo->ruta_archivo, $adjunto->ruta);
        Storage::disk('local')->assertExists($archivo->ruta_archivo);
        Storage::disk('local')->assertExists($adjunto->ruta);
        $this->assertDatabaseHas('archivos', ['id' => $archivo->id]);
    }

    // --- destroy -------------------------------------------------------------

    public function test_destroy_deletes_record_and_physical_file(): void
    {
        $envio = $this->envio();
        Storage::disk('local')->put('correo/envios/'.$envio->id.'/adjuntos/x.pdf', 'contenido');
        $adjunto = CorreoAdjunto::factory()->create(['envio_id' => $envio->id, 'ruta' => 'correo/envios/'.$envio->id.'/adjuntos/x.pdf']);

        $response = $this->actingAs(User::factory()->create())->deleteJson(
            route('admin.correo.envios.adjuntos.destroy', [$envio, $adjunto])
        );

        $response->assertOk()->assertJson(['ok' => true, 'deleted' => true]);
        $this->assertDatabaseMissing('correo_adjuntos', ['id' => $adjunto->id]);
        Storage::disk('local')->assertMissing($adjunto->ruta);
    }

    public function test_destroy_returns_404_when_adjunto_belongs_to_another_envio(): void
    {
        $envioA = $this->envio();
        $envioB = $this->envio();
        $adjunto = CorreoAdjunto::factory()->create(['envio_id' => $envioB->id]);

        $response = $this->actingAs(User::factory()->create())->deleteJson(
            route('admin.correo.envios.adjuntos.destroy', [$envioA, $adjunto])
        );

        $response->assertStatus(404);
        $this->assertDatabaseHas('correo_adjuntos', ['id' => $adjunto->id]);
    }

    // --- EnviadorCorreo --------------------------------------------------------

    public function test_enviador_attaches_files_to_the_mailable(): void
    {
        Mail::fake();

        $envio = $this->envio();
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id]);

        Storage::disk('local')->put('correo/envios/'.$envio->id.'/adjuntos/a.pdf', 'contenido a');
        Storage::disk('local')->put('correo/envios/'.$envio->id.'/adjuntos/b.pdf', 'contenido b');
        CorreoAdjunto::factory()->create(['envio_id' => $envio->id, 'ruta' => 'correo/envios/'.$envio->id.'/adjuntos/a.pdf', 'nombre' => 'A.pdf']);
        CorreoAdjunto::factory()->create(['envio_id' => $envio->id, 'ruta' => 'correo/envios/'.$envio->id.'/adjuntos/b.pdf', 'nombre' => 'B.pdf']);

        app(EnviadorCorreo::class)->enviar($envio);

        Mail::assertSent(CorreoPlantillaMail::class, fn (CorreoPlantillaMail $mail) => count($mail->attachments()) === 2);
    }
}
