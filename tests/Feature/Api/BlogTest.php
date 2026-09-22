<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoArticulo;
use App\Models\Articulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'articulo-de-prueba',
            'titulo' => 'Artículo de prueba',
            'meta_title' => 'Artículo de prueba para SEO',
            'meta_description' => str_repeat('Contenido de meta description de prueba con suficiente longitud. ', 2),
            'resumen' => 'Resumen del artículo de prueba.',
            'contenido' => str_repeat('Contenido largo de prueba para pasar la validación mínima. ', 20),
            'cluster' => \App\Support\Clusters::slugs()[0],
            'estado' => EstadoArticulo::Borrador->value,
            'autor_id' => User::factory()->create()->id,
        ], $overrides);
    }

    public function test_index_requiere_token(): void
    {
        $this->getJson('/api/v1/blog/articulos')->assertUnauthorized();
    }

    public function test_store_crea_articulo_con_html_renderizado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['blog:escribir']);

        $response = $this->postJson('/api/v1/blog/articulos', $this->payload())->assertCreated();

        $articulo = Articulo::find($response->json('data.id'));
        $this->assertNotEmpty($articulo->contenido_html);
        $this->assertGreaterThan(0, $articulo->palabras);
    }

    public function test_store_sin_habilidad_de_escritura_da_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['blog:leer']);

        $this->postJson('/api/v1/blog/articulos', $this->payload())->assertForbidden();
    }

    public function test_publicar_sella_fecha_publicacion(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $store = $this->postJson('/api/v1/blog/articulos', $this->payload())->assertCreated();

        // Articulo::getRouteKeyName() es 'slug' (igual que el panel admin):
        // el binding implícito de {articulo} espera el slug, no el id.
        $response = $this->postJson("/api/v1/blog/articulos/{$store->json('data.slug')}/publicar")->assertOk();

        $this->assertSame(EstadoArticulo::Publicado->value, $response->json('data.estado'));
        $this->assertNotNull($response->json('data.fecha_publicacion'));
    }

    public function test_previsualizar_devuelve_html_y_palabras(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $response = $this->postJson('/api/v1/blog/previsualizar', [
            'contenido' => "# Título\n\nUn párrafo de prueba.",
        ])->assertOk();

        $this->assertStringContainsString('Título', $response->json('data.html'));
    }

    public function test_index_filtra_por_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        Articulo::factory()->create(['estado' => EstadoArticulo::Borrador]);
        Articulo::factory()->create(['estado' => EstadoArticulo::Publicado, 'fecha_publicacion' => now()]);

        $this->getJson('/api/v1/blog/articulos?estado=publicado')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_destroy_elimina_articulo(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $articulo = Articulo::factory()->create();

        $this->deleteJson("/api/v1/blog/articulos/{$articulo->slug}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('articulos', ['id' => $articulo->id]);
    }
}
