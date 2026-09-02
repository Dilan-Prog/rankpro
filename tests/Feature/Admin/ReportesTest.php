<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Models\User;
use App\Support\Reportes\Plantillas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD del reporte en sí (ReportesController). Lo que más importa aquí es el
 * sembrado de la plantilla en store(): un reporte nace con la estructura de su
 * área ya puesta, y si eso se rompe el equipo se encuentra un reporte vacío.
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * No hay ReporteFactory: helper a mano, como KeywordImportTest::lista() y
     * SeoOnPageAccionTest::campana().
     */
    private function reporte(array $overrides = []): Reporte
    {
        return Reporte::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte mensual de prueba',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte mensual agosto',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
        ], $overrides);
    }

    // --- store: sembrado de plantilla ----------------------------------

    /**
     * @dataProvider areas
     */
    public function test_store_seeds_exactly_the_template_sections_in_order(string $area): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.reportes.store'), $this->payload([
            'area' => $area,
        ]));

        $response->assertCreated();

        $reporte = Reporte::latest('id')->first();
        $plantilla = Plantillas::para(AreaReporte::from($area));
        $secciones = $reporte->secciones()->get();

        $this->assertCount(count($plantilla), $secciones);

        foreach ($plantilla as $i => $esperada) {
            $seccion = $secciones[$i];

            $this->assertSame($esperada['tipo']->value, $seccion->tipo->value);
            $this->assertSame($esperada['titulo'], $seccion->titulo);
            $this->assertSame($i + 1, $seccion->orden);
            $this->assertEquals($esperada['contenido'], $seccion->contenido);
            $this->assertTrue($seccion->visible);
        }
    }

    /** @return array<string, array<int, string>> */
    public static function areas(): array
    {
        return [
            'seo' => ['seo'],
            'ads' => ['ads'],
            'web' => ['web'],
        ];
    }

    public function test_store_seo_seeds_consultas_table_with_its_eight_columns_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('admin.reportes.store'), $this->payload(['area' => 'seo']))
            ->assertCreated();

        $consultas = ReporteSeccion::where('titulo', 'Consultas')->firstOrFail();

        $this->assertSame('tabla', $consultas->tipo->value);
        $this->assertCount(8, $consultas->contenido['columnas']);
        $this->assertSame(
            ['consulta', 'clics', 'impresiones', 'ctr', 'posicion', 'intencion', 'pagina_serp', 'oportunidad'],
            array_column($consultas->contenido['columnas'], 'clave')
        );
        // El modo de total por columna también nace puesto: es lo que hace que
        // el CTR agregado del entregable salga bien sin tocar nada.
        $this->assertEquals([
            'clics' => 'suma',
            'impresiones' => 'suma',
            'ctr' => 'ctr',
            'posicion' => 'ponderado',
        ], $consultas->contenido['totales']);
        $this->assertSame([], $consultas->contenido['filas']);
    }

    // --- store: respuesta y campos ------------------------------------

    public function test_store_returns_201_with_show_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.reportes.store'), $this->payload());

        $response->assertCreated();

        $reporte = Reporte::latest('id')->first();
        $response->assertJsonPath('show_url', route('admin.reportes.show', $reporte));
        $response->assertJsonPath('reporte.id', $reporte->id);
        $response->assertJsonPath('reporte.titulo', 'Reporte mensual agosto');
    }

    public function test_store_creates_reporte_in_borrador_with_creado_por_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('admin.reportes.store'), $this->payload(['titulo' => 'Reporte con autor']))
            ->assertCreated();

        $reporte = Reporte::where('titulo', 'Reporte con autor')->firstOrFail();

        $this->assertSame(EstadoReporte::Borrador, $reporte->estado);
        $this->assertSame($user->id, $reporte->creado_por);
        $this->assertNull($reporte->numero);
        $this->assertNull($reporte->fecha_emision);
    }

    // --- store: validación ---------------------------------------------

    public function test_store_rejects_periodo_fin_before_periodo_inicio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.reportes.store'), $this->payload([
            'periodo_inicio' => '2026-08-31',
            'periodo_fin' => '2026-08-01',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('periodo_fin');
        $this->assertSame(0, Reporte::count());
    }

    public function test_store_rejects_unknown_cliente(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.reportes.store'), $this->payload([
            'cliente_id' => 999999,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cliente_id');
        $this->assertSame(0, Reporte::count());
    }

    public function test_store_rejects_unknown_area(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.reportes.store'), $this->payload([
            'area' => 'inexistente',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('area');
    }

    // --- update ---------------------------------------------------------

    public function test_update_changes_editable_fields(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();

        $response = $this->actingAs($user)->putJson(route('admin.reportes.update', $reporte), [
            'titulo' => 'Título corregido',
            'periodo_inicio' => '2026-07-01',
            'periodo_fin' => '2026-07-31',
            'estado' => EstadoReporte::Entregado->value,
            'notas_alcance' => 'Se amplió el alcance a dos dominios.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('titulo', 'Título corregido');
        $response->assertJsonPath('estado', 'entregado');

        $reporte->refresh();
        $this->assertSame('Título corregido', $reporte->titulo);
        $this->assertSame(EstadoReporte::Entregado, $reporte->estado);
        $this->assertSame('2026-07-31', $reporte->periodo_fin->format('Y-m-d'));
        $this->assertSame('Se amplió el alcance a dos dominios.', $reporte->notas_alcance);
    }

    /**
     * `area` y `cliente_id` son inmutables. El controlador no los declara en
     * las reglas de update, así que `validate()` los descarta y `update()`
     * nunca los ve: se ignoran en silencio, no se rechazan.
     */
    public function test_update_ignores_attempts_to_change_area_and_cliente(): void
    {
        $user = User::factory()->create();
        $clienteOriginal = Cliente::factory()->create();
        $otroCliente = Cliente::factory()->create();
        $reporte = $this->reporte([
            'cliente_id' => $clienteOriginal->id,
            'area' => AreaReporte::Seo->value,
        ]);

        $response = $this->actingAs($user)->putJson(route('admin.reportes.update', $reporte), [
            'titulo' => 'Reporte mensual de prueba',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
            'area' => AreaReporte::Ads->value,
            'cliente_id' => $otroCliente->id,
        ]);

        $response->assertOk();

        $reporte->refresh();
        $this->assertSame(AreaReporte::Seo, $reporte->area);
        $this->assertSame($clienteOriginal->id, $reporte->cliente_id);
        $this->assertNotSame($otroCliente->id, $reporte->cliente_id);
    }

    public function test_update_rejects_periodo_fin_before_periodo_inicio(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();

        $response = $this->actingAs($user)->putJson(route('admin.reportes.update', $reporte), [
            'titulo' => 'Reporte mensual de prueba',
            'periodo_inicio' => '2026-08-31',
            'periodo_fin' => '2026-08-01',
            'estado' => EstadoReporte::Borrador->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('periodo_fin');
    }

    // --- destroy ---------------------------------------------------------

    public function test_destroy_soft_deletes_the_reporte(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte();

        $response = $this->actingAs($user)->deleteJson(route('admin.reportes.destroy', $reporte));

        $response->assertOk();
        $response->assertJson(['deleted' => true]);

        $this->assertSoftDeleted('reportes', ['id' => $reporte->id]);
        // Soft delete: las secciones siguen ahí (solo el force delete las tira).
        $this->assertSame(0, Reporte::count());
        $this->assertSame(1, Reporte::withTrashed()->count());
    }

    // --- index / show ----------------------------------------------------

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->reporte(['titulo' => 'Reporte listado']);

        $response = $this->actingAs($user)->get(route('admin.reportes.index'));

        $response->assertOk();
        $response->assertViewIs('admin.reportes.index');
        $response->assertViewHas('reportes');
        $response->assertSee('Reporte listado', false);
    }

    public function test_show_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $reporte = $this->reporte(['titulo' => 'Reporte detalle']);

        $response = $this->actingAs($user)->get(route('admin.reportes.show', $reporte));

        $response->assertOk();
        $response->assertViewIs('admin.reportes.show');
        $response->assertViewHas('reporte');
        $response->assertViewHas('tiposSeccion');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.reportes.index'))->assertRedirect('/login');
    }

    public function test_show_requires_authentication(): void
    {
        $reporte = $this->reporte();

        $this->get(route('admin.reportes.show', $reporte))->assertRedirect('/login');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson(route('admin.reportes.store'), $this->payload())->assertUnauthorized();

        $this->assertSame(0, Reporte::count());
    }
}
