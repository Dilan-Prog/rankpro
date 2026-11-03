<?php

namespace Tests\Feature\Admin;

use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Regresion: un adjunto con `nombre` sin extension se adjuntaba sin ella. */
class CorreoAdjuntoNombreExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_nombre_sin_extension_recibe_la_del_archivo_validado(): void
    {
        Storage::fake('local');
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => CorreoPlantilla::factory()->create()->id]);

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.adjuntos.store', $envio),
            ['nombre' => 'Propuesta final', 'archivo' => UploadedFile::fake()->create('origen.pdf', 10, 'application/pdf')]
        );

        $response->assertCreated()->assertJsonPath('nombre', 'Propuesta final.pdf');
    }
}
