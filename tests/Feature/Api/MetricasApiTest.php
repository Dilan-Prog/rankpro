<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetricasApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(array $habilidades): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user, $habilidades);

        return $user;
    }

    private function metrica(Cliente $cliente, array $extra = []): MetricaCliente
    {
        return MetricaCliente::create($extra + [
            'cliente_id' => $cliente->id,
            'nombre' => 'Llamadas',
            'unidad' => 'llamadas',
            'meta' => 100,
            'sentido' => 'mayor',
            'umbral_amarillo_pct' => 80,
        ]);
    }

    private function valores(MetricaCliente $m): \Illuminate\Support\Collection
    {
        return MetricaClienteValor::where('metrica_id', $m->id)->get();
    }

    // --- auth ------------------------------------------------------------

    public function test_sin_token_es_401(): void
    {
        $m = $this->metrica(Cliente::factory()->create());
        $c = $m->cliente_id;

        $this->getJson('/api/v1/metricas')->assertUnauthorized();
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 1])->assertUnauthorized();
        $this->postJson("/api/v1/clientes/{$c}/metricas/valores", [])->assertUnauthorized();
    }

    public function test_sin_habilidad_es_403(): void
    {
        $this->como(['clientes:escribir', 'seo:leer']);
        $m = $this->metrica(Cliente::factory()->create());

        $this->getJson('/api/v1/metricas')->assertForbidden();
        $this->getJson("/api/v1/metricas/{$m->id}/valores")->assertForbidden();
        $this->postJson("/api/v1/clientes/{$m->cliente_id}/metricas/valores", [])->assertForbidden();
    }

    public function test_solo_lectura_no_puede_escribir(): void
    {
        $this->como(['metricas:leer']);
        $m = $this->metrica(Cliente::factory()->create());

        $this->getJson('/api/v1/metricas')->assertOk();
        $this->postJson('/api/v1/metricas', ['cliente_id' => $m->cliente_id, 'nombre' => 'X'])->assertForbidden();
        $this->putJson("/api/v1/metricas/{$m->id}", ['nombre' => 'Y'])->assertForbidden();
        $this->deleteJson("/api/v1/metricas/{$m->id}")->assertForbidden();
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 1])->assertForbidden();
        $this->postJson("/api/v1/clientes/{$m->cliente_id}/metricas/valores", [])->assertForbidden();
    }

    // --- definiciones ----------------------------------------------------

    public function test_index_pagina_y_filtra_por_cliente_y_activa(): void
    {
        $this->como(['metricas:leer']);
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->metrica($a, ['nombre' => 'Uno', 'orden' => 1]);
        $this->metrica($a, ['nombre' => 'Dos', 'orden' => 2, 'activa' => false]);
        $this->metrica($b, ['nombre' => 'Tres']);

        $this->getJson("/api/v1/metricas?cliente_id={$a->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/metricas?cliente_id={$a->id}&activa=1")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Uno');
        $this->getJson("/api/v1/metricas?cliente_id={$a->id}&activa=0")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Dos');
        $this->getJson('/api/v1/metricas?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.ultima_pagina', 2);
    }

    public function test_store_crea_con_defaults_y_orden_consecutivo(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $this->metrica($cliente, ['orden' => 4]);

        $this->postJson('/api/v1/metricas', ['cliente_id' => $cliente->id, 'nombre' => 'Cotizaciones', 'meta' => '1,5'])
            ->assertCreated()
            ->assertJsonPath('data.sentido', 'mayor')
            ->assertJsonPath('data.umbral_amarillo_pct', 80)
            ->assertJsonPath('data.orden', 5)
            ->assertJsonPath('data.activa', true);
    }

    public function test_store_valida(): void
    {
        $this->como(['metricas:escribir']);

        $this->postJson('/api/v1/metricas', ['nombre' => 'X', 'sentido' => 'raro', 'umbral_amarillo_pct' => 500])
            ->assertStatus(422)->assertJsonValidationErrors(['cliente_id', 'sentido', 'umbral_amarillo_pct']);
    }

    public function test_show_update_destroy_y_404(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-10-01', 'valor' => 5]);

        $this->getJson("/api/v1/metricas/{$m->id}")->assertOk()->assertJsonPath('data.nombre', 'Llamadas');
        $this->putJson("/api/v1/metricas/{$m->id}", ['activa' => false, 'meta' => 50, 'cliente_id' => 999])
            ->assertOk()->assertJsonPath('data.activa', false);
        $this->assertSame(50.0, (float) $m->fresh()->meta);
        $this->assertSame($m->cliente_id, $m->fresh()->cliente_id, 'el cliente no se reasigna');

        $this->deleteJson("/api/v1/metricas/{$m->id}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('metricas_cliente', ['id' => $m->id]);
        $this->assertDatabaseMissing('metrica_cliente_valores', ['metrica_id' => $m->id]);
        $this->getJson("/api/v1/metricas/{$m->id}")->assertNotFound();
        $this->putJson('/api/v1/metricas/999999', ['nombre' => 'X'])->assertNotFound();
    }

    // --- valores ---------------------------------------------------------

    public function test_put_valor_es_idempotente_y_normaliza_al_dia_uno(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());

        foreach ([1, 2] as $_) {
            $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 120, 'nota' => 'GSC'])
                ->assertOk()
                ->assertJsonPath('data.periodo', '2026-10')
                ->assertJsonPath('data.semaforo', 'verde')
                ->assertJsonPath('data.accion', 'guardado');
        }

        $this->assertCount(1, $this->valores($m));
        $this->assertSame('2026-10-01', $this->valores($m)->first()->periodo->toDateString());
    }

    public function test_put_valor_actualiza_y_conserva_la_nota_si_no_se_envia(): void
    {
        $user = $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 10, 'nota' => 'a mano'])->assertOk();

        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 70])->assertOk()
            ->assertJsonPath('data.semaforo', 'rojo')->assertJsonPath('data.nota', 'a mano');
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 85, 'nota' => null])->assertOk()
            ->assertJsonPath('data.semaforo', 'amarillo')->assertJsonPath('data.nota', null);

        $v = $this->valores($m)->first();
        $this->assertSame(85.0, (float) $v->valor);
        $this->assertSame($user->id, $v->registrado_por);
    }

    public function test_valor_vacio_borra_y_cero_explicito_se_guarda(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 30])->assertOk();

        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => null])->assertOk()
            ->assertJsonPath('data.accion', 'borrado')->assertJsonPath('data.semaforo', 'sin_dato');
        $this->assertCount(0, $this->valores($m), 'vacío borra, no deja un 0');

        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => ''])->assertOk()
            ->assertJsonPath('data.accion', 'borrado');
        $this->assertCount(0, $this->valores($m));

        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 0])->assertOk()
            ->assertJsonPath('data.accion', 'guardado');
        $this->assertCount(1, $this->valores($m));
        $this->assertSame(0.0, (float) $this->valores($m)->first()->valor);
    }

    public function test_put_valor_valida_periodo_y_valor(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());

        $this->putJson("/api/v1/metricas/{$m->id}/valores/octubre", ['valor' => 1])->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-13", ['valor' => 1])->assertStatus(422);
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", ['valor' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('valor');
        $this->putJson("/api/v1/metricas/{$m->id}/valores/2026-10", [])->assertStatus(422)->assertJsonValidationErrors('valor');
        $this->putJson('/api/v1/metricas/999999/valores/2026-10', ['valor' => 1])->assertNotFound();
        $this->assertCount(0, $this->valores($m));
    }

    public function test_get_valores_filtra_por_rango_y_calcula_semaforo(): void
    {
        $this->como(['metricas:leer']);
        $m = $this->metrica(Cliente::factory()->create());
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-08-01', 'valor' => 100]);
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-09-01', 'valor' => 85]);
        MetricaClienteValor::create(['metrica_id' => $m->id, 'periodo' => '2026-10-01', 'valor' => 10]);

        $r = $this->getJson("/api/v1/metricas/{$m->id}/valores?desde=2026-09&hasta=2026-10")->assertOk();
        $r->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.periodo', '2026-09')->assertJsonPath('data.0.semaforo', 'amarillo')
            ->assertJsonPath('data.1.semaforo', 'rojo')
            ->assertJsonPath('meta.metrica.id', $m->id)->assertJsonPath('meta.desde', '2026-09');

        $this->getJson("/api/v1/metricas/{$m->id}/valores?desde=2026-10&hasta=2026-09")->assertStatus(422);
        $this->getJson("/api/v1/metricas/{$m->id}/valores?desde=hoy")->assertStatus(422);
        $this->getJson('/api/v1/metricas/999999/valores')->assertNotFound();
    }

    // --- lote ------------------------------------------------------------

    public function test_lote_guarda_por_id_y_por_nombre_sin_importar_mayusculas_y_es_idempotente(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $llamadas = $this->metrica($cliente);
        $clics = $this->metrica($cliente, ['nombre' => 'Clics WhatsApp', 'meta' => 10]);
        $cuerpo = ['periodo' => '2026-10', 'valores' => [
            ['nombre' => 'llamadas', 'valor' => 120, 'nota' => 'GA4'],
            ['metrica_id' => $clics->id, 'valor' => 4],
        ]];

        foreach ([1, 2] as $_) {
            $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", $cuerpo)
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.metrica_id', $llamadas->id)
                ->assertJsonPath('data.0.semaforo', 'verde')
                ->assertJsonPath('data.1.semaforo', 'rojo')
                ->assertJsonPath('meta.periodo', '2026-10');
        }

        $this->assertSame(2, MetricaClienteValor::count(), 'reintentar no duplica');
        $this->assertSame('GA4', $this->valores($llamadas)->first()->nota);
    }

    public function test_lote_con_vacio_borra_y_con_cero_guarda(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $b = $this->metrica($cliente, ['nombre' => 'Ventas']);
        MetricaClienteValor::create(['metrica_id' => $a->id, 'periodo' => '2026-10-01', 'valor' => 9]);

        $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => '2026-10', 'valores' => [
            ['metrica_id' => $a->id, 'valor' => null],
            ['metrica_id' => $b->id, 'valor' => 0],
        ]])->assertOk()->assertJsonPath('data.0.accion', 'borrado')->assertJsonPath('data.1.accion', 'guardado');

        $this->assertCount(0, $this->valores($a));
        $this->assertCount(1, $this->valores($b));
    }

    public function test_lote_es_atomico_y_reporta_el_indice_que_falla(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $this->metrica($cliente);

        $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => '2026-10', 'valores' => [
            ['nombre' => 'Llamadas', 'valor' => 5],
            ['nombre' => 'No existe', 'valor' => 7],
        ]])->assertStatus(422)->assertJsonValidationErrors(['valores.1']);

        $this->assertSame(0, MetricaClienteValor::count(), 'no se guarda nada si una entrada falla');
    }

    public function test_lote_rechaza_metrica_de_otro_cliente_ambiguas_y_repetidas(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $ajena = $this->metrica(Cliente::factory()->create(), ['nombre' => 'Ajena']);
        $this->metrica($cliente, ['nombre' => 'Doble']);
        $this->metrica($cliente, ['nombre' => 'doble']);
        $propia = $this->metrica($cliente, ['nombre' => 'Propia']);

        $r = $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => '2026-10', 'valores' => [
            ['metrica_id' => $ajena->id, 'valor' => 1],
            ['nombre' => 'DOBLE', 'valor' => 1],
            ['metrica_id' => $propia->id, 'valor' => 1],
            ['nombre' => 'propia', 'valor' => 2],
        ]])->assertStatus(422);

        $r->assertJsonValidationErrors(['valores.0', 'valores.1', 'valores.3']);
        $this->assertArrayNotHasKey('valores.2', $r->json('errors'));
        $this->assertSame(0, MetricaClienteValor::count());
    }

    public function test_lote_valida_cuerpo_y_cliente_inexistente(): void
    {
        $this->como(['metricas:escribir']);
        $cliente = Cliente::factory()->create();
        $m = $this->metrica($cliente);

        $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => '2026-10'])
            ->assertStatus(422)->assertJsonValidationErrors('valores');
        $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => 'x', 'valores' => [['metrica_id' => $m->id, 'valor' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->postJson("/api/v1/clientes/{$cliente->id}/metricas/valores", ['periodo' => '2026-10', 'valores' => [['valor' => 1], ['metrica_id' => $m->id, 'valor' => 'abc']]])
            ->assertStatus(422)->assertJsonValidationErrors(['valores.0.metrica_id', 'valores.1.valor']);
        $this->postJson('/api/v1/clientes/999999/metricas/valores', ['periodo' => '2026-10', 'valores' => [['nombre' => 'x', 'valor' => 1]]])
            ->assertNotFound();
    }

    public function test_aislamiento_por_cliente_en_el_lote(): void
    {
        $this->como(['metricas:escribir']);
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $ma = $this->metrica($a);
        $mb = $this->metrica($b);

        $this->postJson("/api/v1/clientes/{$a->id}/metricas/valores", ['periodo' => '2026-10', 'valores' => [['nombre' => 'Llamadas', 'valor' => 50]]])->assertOk();

        $this->assertCount(1, $this->valores($ma));
        $this->assertCount(0, $this->valores($mb), 'el mismo nombre en otro cliente no se toca');
    }

    public function test_openapi_incluye_las_rutas_nuevas(): void
    {
        $paths = $this->getJson('/api/v1/openapi.json')->assertOk()->json('paths');

        $this->assertArrayHasKey('post', $paths['/clientes/{cliente}/metricas/valores']);
        $this->assertArrayHasKey('put', $paths['/metricas/{metrica}/valores/{periodo}']);
        $this->assertArrayHasKey('get', $paths['/captura']);
        $this->assertArrayHasKey('post', $paths['/reportes/programados/{programacion}/enviar']);
    }

    public function test_pantallas_de_tokens_y_docs_ofrecen_y_documentan_lo_nuevo(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.integraciones.api.index'))->assertOk()
            ->assertSee('metricas:escribir')->assertSee('captura:leer');
        $this->get(route('admin.integraciones.api.docs'))->assertOk()
            ->assertSee('/clientes/12/metricas/valores', false)->assertSee('/captura', false)
            ->assertSee('/reportes/programados/{id}/enviar', false);
    }

    public function test_update_con_orden_null_no_da_500(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create(), ['orden' => 4]);

        $this->putJson("/api/v1/metricas/{$m->id}", ['orden' => null, 'nombre' => 'Nuevo'])->assertOk();

        $this->assertSame(4, $m->fresh()->orden);
        $this->assertSame('Nuevo', $m->fresh()->nombre);
    }

    public function test_meta_negativa_es_422_en_store_y_update(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica(Cliente::factory()->create());

        $this->postJson('/api/v1/metricas', ['cliente_id' => $m->cliente_id, 'nombre' => 'X', 'meta' => -100])
            ->assertStatus(422)->assertJsonValidationErrors('meta');
        $this->putJson("/api/v1/metricas/{$m->id}", ['meta' => '-1,5'])->assertStatus(422)->assertJsonValidationErrors('meta');
    }

    public function test_lote_con_entradas_raras_da_422_y_no_500(): void
    {
        $this->como(['metricas:escribir']);
        $m = $this->metrica($cliente = Cliente::factory()->create());
        $url = "/api/v1/clientes/{$cliente->id}/metricas/valores";

        // metrica_id 0 sin nombre: antes caía en la rama de nombre y rompía.
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [['metrica_id' => 0, 'valor' => 1]]])->assertStatus(422);
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [['metrica_id' => null, 'nombre' => '', 'valor' => 1]]])->assertStatus(422);
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [['metrica_id' => [1], 'valor' => 1]]])->assertStatus(422);
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [['nombre' => ['x'], 'valor' => 1]]])->assertStatus(422);
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [['nombre' => '   ', 'valor' => 1]]])->assertStatus(422);
        $this->postJson($url, ['periodo' => '2026-10', 'valores' => [null]])->assertStatus(422);
        $this->assertSame(0, $this->valores($m)->count());
    }

    public function test_rango_maximo_de_valores_es_60_meses_inclusive(): void
    {
        $this->como(['metricas:leer']);
        $m = $this->metrica(Cliente::factory()->create());

        $this->getJson("/api/v1/metricas/{$m->id}/valores?desde=2021-11&hasta=2026-10")->assertOk();
        $this->getJson("/api/v1/metricas/{$m->id}/valores?desde=2021-10&hasta=2026-10")->assertStatus(422);
    }
}
