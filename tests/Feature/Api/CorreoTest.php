<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoEnvioCorreo;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de /api/v1/correo/{plantillas,envios}. Mail::fake() igual que
 * Admin\CorreoEnviosTest: se comprueba el estado resultante y qué salió, no
 * el SMTP real.
 */
class CorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-09-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function plantilla(array $overrides = []): CorreoPlantilla
    {
        return CorreoPlantilla::factory()->create(array_merge([
            'asunto' => 'Reporte de {{mes}}',
            'bloques' => [
                ['tipo' => 'heading', 'texto' => 'Hola'],
                ['tipo' => 'text', 'texto' => 'Hola {{contacto}}.'],
            ],
        ], $overrides));
    }

    // --- plantillas --------------------------------------------------------

    public function test_plantillas_index_requiere_token(): void
    {
        $this->getJson('/api/v1/correo/plantillas')->assertUnauthorized();
    }

    public function test_plantillas_store_y_duplicar(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['correo:escribir']);

        $store = $this->postJson('/api/v1/correo/plantillas', [
            'nombre' => 'Plantilla API',
            'categoria' => 'otros',
        ])->assertCreated();

        $id = $store->json('data.id');

        $this->postJson("/api/v1/correo/plantillas/{$id}/duplicar")
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Plantilla API (copia)');
    }

    public function test_plantillas_preview_devuelve_html(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $response = $this->postJson('/api/v1/correo/plantillas/preview', [
            'bloques' => [['tipo' => 'heading', 'texto' => 'Hola']],
        ])->assertOk();

        $this->assertStringContainsString('Hola', $response->json('data.html'));
    }

    // --- envios --------------------------------------------------------------

    public function test_envios_store_borrador(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['correo:escribir']);
        $plantilla = $this->plantilla();

        $response = $this->postJson('/api/v1/correo/envios', [
            'plantilla_id' => $plantilla->id,
            'asunto' => 'Reporte de agosto',
            'variables' => ['mes' => 'agosto 2026'],
            'destinatarios' => [['email' => 'alguien@ejemplo.com', 'nombre' => 'Alguien']],
            'accion' => 'borrador',
        ])->assertCreated();

        $this->assertSame(EstadoEnvioCorreo::Borrador->value, $response->json('data.estado'));
        Mail::assertNothingSent();
    }

    public function test_envios_store_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['correo:leer']);
        $plantilla = $this->plantilla();

        $this->postJson('/api/v1/correo/envios', [
            'plantilla_id' => $plantilla->id,
            'asunto' => 'x',
            'accion' => 'borrador',
        ])->assertForbidden();
    }

    public function test_envios_enviar_manda_a_todos_los_destinatarios_pendientes(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id]);
        CorreoDestinatario::factory()->count(2)->create(['envio_id' => $envio->id]);

        $response = $this->postJson("/api/v1/correo/envios/{$envio->id}/enviar")->assertOk();

        $this->assertSame(EstadoEnvioCorreo::Enviado->value, $response->json('data.estado'));
        Mail::assertSent(\App\Mail\CorreoPlantillaMail::class, 2);
    }

    public function test_envios_programar_deja_el_envio_para_el_scheduler(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id]);

        $response = $this->postJson("/api/v1/correo/envios/{$envio->id}/programar", [
            'programado_para' => '2026-09-21 13:00',
        ])->assertOk();

        $this->assertSame(EstadoEnvioCorreo::Programado->value, $response->json('data.estado'));
        Mail::assertNothingSent();
    }

    public function test_envios_cancelar_un_programado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $envio = CorreoEnvio::factory()->create([
            'plantilla_id' => $this->plantilla()->id,
            'estado' => EstadoEnvioCorreo::Programado->value,
            'programado_para' => now()->addHour(),
        ]);

        $this->postJson("/api/v1/correo/envios/{$envio->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoEnvioCorreo::Cancelado->value);
    }

    public function test_envios_prueba_manda_un_correo_de_prueba(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $plantilla = $this->plantilla();

        $this->postJson('/api/v1/correo/envios/prueba', [
            'plantilla_id' => $plantilla->id,
            'asunto' => 'Asunto de prueba',
            'email' => 'yo@rankprosolutions.com.mx',
        ])->assertOk();

        Mail::assertSent(\App\Mail\CorreoPlantillaMail::class, 1);
    }

    public function test_envios_index_filtra_por_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id, 'estado' => EstadoEnvioCorreo::Borrador->value]);
        CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id, 'estado' => EstadoEnvioCorreo::Enviado->value, 'enviado_en' => now()]);

        $this->getJson('/api/v1/correo/envios?estado=enviado')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
