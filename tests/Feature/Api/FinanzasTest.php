<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    use RefreshDatabase;

    private function finanza(array $overrides = []): Finanza
    {
        return Finanza::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'concepto' => 'Factura de prueba',
            'tipo' => 'ingreso',
            'monto' => 1000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
            'mes' => now()->month,
            'anio' => now()->year,
        ], $overrides));
    }

    public function test_index_lista_finanzas(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->finanza();
        $this->finanza();

        $this->getJson('/api/v1/finanzas')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_filtra_por_cliente_y_estado(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();
        $this->finanza(['cliente_id' => $cliente->id, 'estado' => 'pagado']);
        $this->finanza(['estado' => 'pendiente']);

        $this->getJson("/api/v1/finanzas?cliente_id={$cliente->id}&estado=pagado")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_crea_finanza(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/v1/finanzas', [
            'cliente_id' => $cliente->id,
            'concepto' => 'Servicio SEO',
            'tipo' => 'ingreso',
            'monto' => 5000,
            'estado' => 'pendiente',
            'mes' => now()->month,
            'anio' => now()->year,
        ]);

        $response->assertCreated()->assertJsonPath('data.concepto', 'Servicio SEO');
    }

    public function test_pagar_marca_como_pagado_con_fecha_de_hoy(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $finanza = $this->finanza(['estado' => 'pendiente']);

        $response = $this->postJson("/api/v1/finanzas/{$finanza->id}/pagar");

        $response->assertOk()->assertJsonPath('data.estado', 'pagado');
        $this->assertStringStartsWith(now()->toDateString(), $response->json('data.fecha_pago'));
    }

    public function test_resumen_devuelve_totales_de_cartera(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->finanza(['estado' => 'pendiente', 'monto' => 300]);
        $this->finanza(['estado' => 'pagado', 'monto' => 700]);
        $this->finanza(['estado' => 'vencido', 'monto' => 100]);

        $response = $this->getJson('/api/v1/finanzas/resumen')->assertOk();

        $this->assertEquals(300, $response->json('data.pendiente'));
        $this->assertEquals(700, $response->json('data.pagado'));
        $this->assertEquals(100, $response->json('data.vencido'));
    }

    public function test_destroy_elimina_finanza(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $finanza = $this->finanza();

        $this->deleteJson("/api/v1/finanzas/{$finanza->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_escritura_prohibida_con_habilidad_de_solo_lectura(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['finanzas:leer']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/v1/finanzas', [
            'cliente_id' => $cliente->id,
            'concepto' => 'X',
            'tipo' => 'ingreso',
            'monto' => 100,
            'estado' => 'pendiente',
            'mes' => now()->month,
            'anio' => now()->year,
        ])->assertForbidden();
    }
}
