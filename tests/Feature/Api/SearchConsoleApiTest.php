<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscPropiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\LlaveGoogleDePrueba;
use Tests\TestCase;

class SearchConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(array $habilidades): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), $habilidades);
    }

    private function propiedad(): GscPropiedad
    {
        (new GscConexion())->forceFill([
            'id' => 1, 'client_email' => LlaveGoogleDePrueba::CORREO, 'estado' => GscConexion::OK,
            'llave_json' => json_encode(['client_email' => LlaveGoogleDePrueba::CORREO, 'private_key' => LlaveGoogleDePrueba::par()['privada']]),
        ])->save();

        return GscPropiedad::create(['cliente_id' => Cliente::factory()->create(['nombre' => 'Cliente QA'])->id, 'site_url' => 'sc-domain:ejemplo.com']);
    }

    public function test_exige_token_y_habilidad(): void
    {
        $p = $this->propiedad();

        $this->getJson('/api/v1/search-console/propiedades')->assertUnauthorized();
        $this->postJson("/api/v1/search-console/propiedades/{$p->id}/sincronizar")->assertUnauthorized();

        $this->como(['clientes:leer']);
        $this->getJson('/api/v1/search-console/propiedades')->assertForbidden();
    }

    public function test_lista_las_propiedades_sin_exponer_la_llave(): void
    {
        $this->propiedad();
        $this->como(['integraciones:leer']);

        $r = $this->getJson('/api/v1/search-console/propiedades')->assertOk();

        $r->assertJsonPath('data.0.site_url', 'sc-domain:ejemplo.com')->assertJsonPath('data.0.cliente', 'Cliente QA');
        $this->assertStringNotContainsString('PRIVATE KEY', $r->getContent());
        $this->assertStringNotContainsString('private_key', $r->getContent());
    }

    public function test_sincronizar_exige_habilidad_de_escritura(): void
    {
        $p = $this->propiedad();
        Http::fake();
        $this->como(['integraciones:leer']);

        $this->postJson("/api/v1/search-console/propiedades/{$p->id}/sincronizar")->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_sincronizar_importa_y_devuelve_el_resultado(): void
    {
        $p = $this->propiedad();
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600]),
            'www.googleapis.com/*' => Http::response(['rows' => [['keys' => ['2026-09-10'], 'clicks' => 3, 'impressions' => 30, 'ctr' => 0.1, 'position' => 5]]]),
        ]);
        $this->como(['integraciones:escribir']);

        $this->postJson("/api/v1/search-console/propiedades/{$p->id}/sincronizar")
            ->assertOk()
            ->assertJsonPath('data.ultimo_ok', true);

        $this->assertSame(1, $p->metricas()->count());
    }

    public function test_sincronizar_devuelve_409_si_ya_hay_una_en_curso(): void
    {
        $p = $this->propiedad();
        Http::fake();
        $this->como(['integraciones:escribir']);

        $candado = Cache::lock("gsc:sincronizar:{$p->id}", 60);
        $candado->get();

        $this->postJson("/api/v1/search-console/propiedades/{$p->id}/sincronizar")->assertStatus(409);
        Http::assertNothingSent();

        $candado->release();
    }

    public function test_un_fallo_de_google_es_502_con_el_mensaje(): void
    {
        $p = $this->propiedad();
        Cache::flush();
        config(['services.google_search_console.espera_ms' => 0]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600]),
            'www.googleapis.com/*' => Http::response(['error' => ['message' => 'x']], 404),
        ]);
        $this->como(['*']);

        $this->postJson("/api/v1/search-console/propiedades/{$p->id}/sincronizar")
            ->assertStatus(502)
            ->assertJsonPath('data.ultimo_ok', false);
    }
}
