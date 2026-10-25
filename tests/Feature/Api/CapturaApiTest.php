<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapturaApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(array $habilidades): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), $habilidades);
    }

    private function metrica(Cliente $cliente, string $nombre = 'Llamadas'): MetricaCliente
    {
        return MetricaCliente::create([
            'cliente_id' => $cliente->id, 'nombre' => $nombre, 'meta' => 10, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80,
        ]);
    }

    public function test_sin_token_es_401(): void
    {
        $this->getJson('/api/v1/captura')->assertUnauthorized();
    }

    public function test_sin_habilidad_es_403(): void
    {
        $this->como(['metricas:leer', 'clientes:leer']);

        $this->getJson('/api/v1/captura')->assertForbidden();
    }

    public function test_devuelve_resumen_filas_y_lista_plana_de_pendientes(): void
    {
        $this->como(['captura:leer']);
        $pendiente = Cliente::factory()->create(['estado' => 'activo']);
        $completo = Cliente::factory()->create(['estado' => 'activo']);
        $this->metrica($pendiente);
        MetricaClienteValor::create(['metrica_id' => $this->metrica($completo)->id, 'periodo' => '2026-03-01', 'valor' => 5]);
        // Un cliente inactivo no sale en el tablero.
        $this->metrica(Cliente::factory()->create(['estado' => 'pausado']));

        $r = $this->getJson('/api/v1/captura?mes=2026-03')->assertOk();

        $r->assertJsonPath('data.mes', '2026-03')
            ->assertJsonPath('data.resumen.pendientes', 1)
            ->assertJsonPath('data.resumen.total', 2)
            ->assertJsonCount(2, 'data.filas')
            ->assertJsonCount(1, 'data.pendientes')
            ->assertJsonPath('data.pendientes.0.cliente_id', $pendiente->id)
            ->assertJsonPath('data.pendientes.0.fuente', 'metricas')
            ->assertJsonPath('data.pendientes.0.estado', 'falta');

        $url = $r->json('data.pendientes.0.url');
        $this->assertStringContainsString("cliente={$pendiente->id}", $url);
        $this->assertStringContainsString('mes=2026-03', $url);

        $fila = collect($r->json('data.filas'))->firstWhere('cliente_id', $pendiente->id);
        $celda = collect($fila['celdas'])->firstWhere('fuente', 'metricas');
        $this->assertSame(['fuente', 'etiqueta', 'estado', 'hechas', 'total', 'url'], array_keys($celda));
        $this->assertSame('Métricas personalizadas', $celda['etiqueta']);
    }

    public function test_solo_pendientes_oculta_filas_completas_pero_no_cambia_el_resumen(): void
    {
        $this->como(['captura:leer']);
        $pendiente = Cliente::factory()->create(['estado' => 'activo']);
        $completo = Cliente::factory()->create(['estado' => 'activo']);
        $this->metrica($pendiente);
        MetricaClienteValor::create(['metrica_id' => $this->metrica($completo)->id, 'periodo' => '2026-03-01', 'valor' => 5]);

        $this->getJson('/api/v1/captura?mes=2026-03&solo_pendientes=1')->assertOk()
            ->assertJsonCount(1, 'data.filas')
            ->assertJsonPath('data.filas.0.cliente_id', $pendiente->id)
            ->assertJsonPath('data.resumen.total', 2);
        $this->getJson('/api/v1/captura?mes=2026-03&solo_pendientes=0')->assertOk()->assertJsonCount(2, 'data.filas');
    }

    public function test_mes_por_defecto_es_el_actual_y_uno_invalido_es_422(): void
    {
        $this->como(['captura:leer']);

        $this->getJson('/api/v1/captura')->assertOk()->assertJsonPath('data.mes', now()->format('Y-m'))
            ->assertJsonPath('data.resumen.total', 0);
        $this->getJson('/api/v1/captura?mes=marzo')->assertStatus(422)->assertJsonValidationErrors('mes');
        $this->getJson('/api/v1/captura?solo_pendientes=quiza')->assertStatus(422)->assertJsonValidationErrors('solo_pendientes');
    }

    public function test_un_token_con_todo_puede_leer(): void
    {
        $this->como(['*']);

        $this->getJson('/api/v1/captura')->assertOk();
    }
}
