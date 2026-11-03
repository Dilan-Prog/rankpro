<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\KeywordLista;
use App\Models\SeoCampana;
use App\Models\SeoPosicion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la auditoría QA: variación de posiciones SEO y validaciones
 * de pertenencia / soft delete (cliente, servicio, listas).
 */
class CorreccionesQaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function campanaSeo(): SeoCampana
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $this->postJson('/admin/seo', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'C', 'url_sitio' => 'https://ejemplo.com'])->assertStatus(201);

        return SeoCampana::latest('id')->first();
    }

    // ---- 2) variación de posiciones -------------------------------------

    public function test_variacion_es_null_si_falta_la_posicion_anterior_o_la_actual(): void
    {
        $campana = $this->campanaSeo();
        $base = ['keyword' => 'k', 'dispositivo' => 'desktop'];

        $this->postJson("/admin/seo/{$campana->id}/posiciones", $base + ['posicion_actual' => 5])->assertStatus(201)->assertJsonPath('variacion', null);
        // Salir del ranking (actual 0) no es "+10".
        $this->postJson("/admin/seo/{$campana->id}/posiciones", $base + ['posicion_actual' => 0, 'posicion_anterior' => 10])->assertStatus(201)->assertJsonPath('variacion', null);
        $this->postJson("/admin/seo/{$campana->id}/posiciones", $base + ['posicion_anterior' => 8])->assertStatus(201)->assertJsonPath('variacion', null);
        $this->postJson("/admin/seo/{$campana->id}/posiciones", $base + ['posicion_actual' => 3, 'posicion_anterior' => 10])->assertStatus(201)->assertJsonPath('variacion', 7);
    }

    public function test_fila_de_posicion_sin_variacion_muestra_guion(): void
    {
        $campana = $this->campanaSeo();
        SeoPosicion::create(['seo_campana_id' => $campana->id, 'cliente_id' => $campana->cliente_id, 'keyword' => 'sinvar', 'posicion_actual' => 5, 'variacion' => null, 'dispositivo' => 'desktop', 'fecha_registro' => now()->toDateString()]);

        $html = view('admin.seo._posicion-row', ['p' => SeoPosicion::first()])->render();
        $this->assertStringContainsString('—', $html);
        $this->assertStringContainsString('color:inherit">—</td>', $html);
    }

    // ---- 3) pertenencia y soft delete ------------------------------------

    public function test_seo_rechaza_servicio_de_otro_cliente(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $servicioDeB = Servicio::factory()->create(['cliente_id' => $b->id, 'tipo' => 'seo']);

        $this->postJson('/admin/seo', ['cliente_id' => $a->id, 'servicio_id' => $servicioDeB->id, 'nombre' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('servicio_id');
        $this->assertSame(0, SeoCampana::count());
    }

    public function test_seo_rechaza_servicio_de_otro_tipo(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $a->id, 'tipo' => 'automatizacion']);

        $this->postJson('/admin/seo', ['cliente_id' => $a->id, 'servicio_id' => $servicio->id, 'nombre' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('servicio_id');
    }

    public function test_ads_rechaza_servicio_de_otro_cliente_o_tipo(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $ajeno = Servicio::factory()->create(['cliente_id' => $b->id, 'tipo' => 'google_ads']);
        $seo = Servicio::factory()->create(['cliente_id' => $a->id, 'tipo' => 'seo']);
        $base = ['cliente_id' => $a->id, 'nombre' => 'X', 'plataforma' => 'google_ads', 'objetivo' => 'leads', 'presupuesto_mensual' => 100];

        $this->postJson('/admin/ads', $base + ['servicio_id' => $ajeno->id])->assertStatus(422)->assertJsonValidationErrors('servicio_id');
        $this->postJson('/admin/ads', $base + ['servicio_id' => $seo->id])->assertStatus(422)->assertJsonValidationErrors('servicio_id');
    }

    public function test_automatizaciones_rechaza_servicio_de_otro_cliente(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $ajeno = Servicio::factory()->create(['cliente_id' => $b->id, 'tipo' => 'automatizacion']);

        $this->post('/admin/automatizaciones', ['cliente_id' => $a->id, 'servicio_id' => $ajeno->id, 'nombre' => 'X'])
            ->assertSessionHasErrors('servicio_id');
    }

    public function test_rechaza_cliente_y_servicio_con_soft_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::factory()->create();
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        $servicio->delete();

        $this->postJson('/admin/seo', ['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'nombre' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('servicio_id');

        $otro = Cliente::factory()->create();
        $otro->delete();
        $this->postJson('/admin/keywords/listas', ['cliente_id' => $otro->id, 'nombre' => 'L', 'estado' => 'en_uso'])
            ->assertStatus(422)->assertJsonValidationErrors('cliente_id');
    }

    public function test_bulk_descartar_ignora_listas_borradas(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::factory()->create();
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'L', 'canal' => 'seo', 'estado' => 'en_uso']);
        $lista->delete();

        $this->postJson(route('admin.keywords.listas.bulk-descartar'), ['ids' => [$lista->id]])
            ->assertStatus(422)->assertJsonValidationErrors('ids.0');
    }
}
