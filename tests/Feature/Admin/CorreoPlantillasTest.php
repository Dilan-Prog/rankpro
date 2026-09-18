<?php

namespace Tests\Feature\Admin;

use App\Models\CorreoPlantilla;
use App\Models\User;
use App\Support\Correo\Bloques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests del CRUD de plantillas de correo (CorreoPlantillasController).
 *
 * Misma convención que PropuestasTest: un test_* por escenario, verbos JSON
 * para los endpoints AJAX (store/update/destroy/duplicar/preview) y get()
 * para el listado. Las respuestas siguen el contrato {ok, row, show_url}.
 */
class CorreoPlantillasTest extends TestCase
{
    use RefreshDatabase;

    // --- helpers -------------------------------------------------------------

    /** Payload completo de update (autosave manda todo el estado del editor). */
    private function payloadUpdate(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Reporte mensual',
            'categoria' => 'reportes',
            'estado' => 'activa',
            'asunto' => 'Tu reporte de {{mes}}',
            'bloques' => [
                ['tipo' => 'heading', 'texto' => 'Resultados de {{mes}}', 'alineacion' => 'centro'],
                ['tipo' => 'text', 'texto' => 'Hola {{contacto}}'],
            ],
            'marca' => [
                'color' => '#1A2332',
                'logo' => 'monograma',
                'logo_url' => null,
                'tagline' => 'Digital Solutions',
                'redes' => [['nombre' => 'Sitio', 'url' => 'https://rankprosolutions.com.mx']],
            ],
            'html_personalizado' => null,
        ], $overrides);
    }

    // --- auth ----------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('admin.correo.plantillas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_json_endpoints_require_authentication(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $this->postJson(route('admin.correo.plantillas.store'), [])->assertStatus(401);
        $this->putJson(route('admin.correo.plantillas.update', $plantilla), [])->assertStatus(401);
        $this->deleteJson(route('admin.correo.plantillas.destroy', $plantilla))->assertStatus(401);
        $this->postJson(route('admin.correo.plantillas.duplicar', $plantilla))->assertStatus(401);
        $this->postJson(route('admin.correo.plantillas.preview'), [])->assertStatus(401);
    }

    // --- index ---------------------------------------------------------------

    public function test_index_lists_plantillas_for_authenticated_user(): void
    {
        $plantilla = CorreoPlantilla::factory()->create(['nombre' => 'Plantilla visible']);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.correo.plantillas.index'));

        $response->assertOk();
        $this->assertTrue($response->viewData('plantillas')->pluck('id')->contains($plantilla->id));
    }

    // --- store ---------------------------------------------------------------

    public function test_store_requires_nombre_and_categoria(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.correo.plantillas.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombre', 'categoria']);
    }

    public function test_store_creates_with_default_blocks_and_returns_show_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.correo.plantillas.store'), [
            'nombre' => 'Bienvenida',
            'categoria' => 'onboarding',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonStructure(['ok', 'show_url', 'row' => ['id', 'nombre', 'categoria', 'estado', 'asunto', 'html_libre', 'usos', 'apertura', 'show_url']]);

        $plantilla = CorreoPlantilla::where('nombre', 'Bienvenida')->firstOrFail();

        $response->assertJsonPath('show_url', route('admin.correo.plantillas.show', $plantilla));
        $response->assertJsonPath('row.nombre', 'Bienvenida');
        $this->assertSame('activa', $plantilla->estado);
        $this->assertSame($user->id, $plantilla->creado_por);
        // Sin asunto explícito, el nombre sirve de asunto.
        $this->assertSame('Bienvenida', $plantilla->asunto);
        $this->assertEquals(Bloques::porDefecto(), $plantilla->bloques);
        // assertEquals: la columna JSON de MySQL reordena las claves del objeto.
        $this->assertEquals(Bloques::marcaPorDefecto(), $plantilla->marca);
    }

    public function test_store_keeps_explicit_asunto(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.store'), [
            'nombre' => 'Factura',
            'categoria' => 'facturacion',
            'asunto' => 'Factura de {{mes}}',
        ])->assertCreated();

        $this->assertSame('Factura de {{mes}}', CorreoPlantilla::where('nombre', 'Factura')->firstOrFail()->asunto);
    }

    public function test_store_rejects_unknown_categoria(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.store'), [
            'nombre' => 'X',
            'categoria' => 'inventada',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('categoria');
    }

    // --- show ----------------------------------------------------------------

    public function test_show_renders_editor_with_plantilla_data(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.correo.plantillas.show', $plantilla));

        $response->assertOk();
        $this->assertSame($plantilla->id, $response->viewData('editorData')['plantilla']['id']);
    }

    // --- update --------------------------------------------------------------

    public function test_update_saves_blocks_and_marca(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate());

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('row.nombre', 'Reporte mensual');

        $plantilla->refresh();
        $this->assertCount(2, $plantilla->bloques);
        $this->assertSame('heading', $plantilla->bloques[0]['tipo']);
        $this->assertSame('Resultados de {{mes}}', $plantilla->bloques[0]['texto']);
        $this->assertSame('#1A2332', $plantilla->marca['color']);
        $this->assertSame('monograma', $plantilla->marca['logo']);
        $this->assertSame('Sitio', $plantilla->marca['redes'][0]['nombre']);
        $this->assertNull($plantilla->html_personalizado);
        $this->assertFalse($plantilla->esHtmlLibre());
    }

    public function test_update_rejects_invalid_block_type(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate([
                'bloques' => [['tipo' => 'carrusel', 'texto' => 'x']],
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('bloques.0.tipo');
        $this->assertEquals(Bloques::porDefecto(), $plantilla->refresh()->bloques);
    }

    public function test_update_rejects_invalid_marca_color(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate([
                'marca' => ['color' => 'verde'],
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('marca.color');
    }

    public function test_update_requires_asunto(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate(['asunto' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('asunto');
    }

    public function test_update_stores_html_libre_and_blank_html_as_null(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate([
            'html_personalizado' => '<p>Hola {{contacto}}</p>',
        ]))->assertOk()->assertJsonPath('row.html_libre', true);

        $this->assertTrue($plantilla->refresh()->esHtmlLibre());

        $this->actingAs($user)->putJson(route('admin.correo.plantillas.update', $plantilla), $this->payloadUpdate([
            'html_personalizado' => "   \n",
        ]))->assertOk()->assertJsonPath('row.html_libre', false);

        $this->assertNull($plantilla->refresh()->html_personalizado);
    }

    // --- preview -------------------------------------------------------------

    public function test_preview_returns_html_with_example_variables(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.preview'), [
            'bloques' => [['tipo' => 'text', 'texto' => 'Hola {{contacto}}, reporte de {{mes}}']],
            'marca' => Bloques::marcaPorDefecto(),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['html']);

        $html = $response->json('html');
        $this->assertStringContainsString('Hola María, reporte de agosto 2026', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_preview_received_variables_win_over_examples(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.preview'), [
            'bloques' => [['tipo' => 'text', 'texto' => 'Hola {{contacto}}']],
            'variables' => ['contacto' => 'Pedro', 'inventada' => 'x'],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Hola Pedro', $response->json('html'));
    }

    public function test_preview_is_lenient_with_half_typed_color_and_unknown_blocks(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.preview'), [
            'bloques' => [['tipo' => 'heading', 'texto' => 'Título'], ['tipo' => 'raro']],
            'marca' => ['color' => '#12'],
        ]);

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Título</h1>', $html);
        $this->assertStringContainsString(Bloques::marcaPorDefecto()['color'], $html);
    }

    public function test_preview_uses_html_libre_when_present(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.plantillas.preview'), [
            'bloques' => [['tipo' => 'heading', 'texto' => 'NO DEBE SALIR']],
            'html_personalizado' => '<p>Libre para {{cliente}}</p>',
        ]);

        $response->assertOk();
        $this->assertSame('<p>Libre para Hotel Fratelli</p>', $response->json('html'));
    }

    // --- duplicar ------------------------------------------------------------

    public function test_duplicar_creates_a_copy_and_returns_show_url(): void
    {
        $user = User::factory()->create();
        $original = CorreoPlantilla::factory()->create(['nombre' => 'Original', 'estado' => 'archivada']);

        $response = $this->actingAs($user)->postJson(route('admin.correo.plantillas.duplicar', $original));

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('row.nombre', 'Original (copia)');

        $copia = CorreoPlantilla::where('nombre', 'Original (copia)')->firstOrFail();

        $response->assertJsonPath('show_url', route('admin.correo.plantillas.show', $copia));
        $this->assertSame(2, CorreoPlantilla::count());
        $this->assertSame('activa', $copia->estado);
        $this->assertSame($user->id, $copia->creado_por);
        $this->assertEquals($original->bloques, $copia->bloques);
        $this->assertSame($original->asunto, $copia->asunto);
    }

    // --- destroy -------------------------------------------------------------

    public function test_destroy_soft_deletes(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->deleteJson(route('admin.correo.plantillas.destroy', $plantilla));

        $response->assertOk();
        $response->assertExactJson(['ok' => true, 'deleted' => true]);
        $this->assertSoftDeleted('correo_plantillas', ['id' => $plantilla->id]);
    }

    public function test_soft_deleted_plantillas_are_excluded_from_index(): void
    {
        $plantilla = CorreoPlantilla::factory()->create();
        $plantilla->delete();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.correo.plantillas.index'));

        $this->assertFalse($response->viewData('plantillas')->pluck('id')->contains($plantilla->id));
    }
}
