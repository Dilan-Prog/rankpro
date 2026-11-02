<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Métricas trabaja con el cliente activo del menú (grupo Marca). */
class MetricasClienteActivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function metrica(Cliente $cliente, string $nombre): MetricaCliente
    {
        return MetricaCliente::create([
            'cliente_id' => $cliente->id, 'nombre' => $nombre, 'unidad' => 'u',
            'meta' => 10, 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80,
        ]);
    }

    public function test_con_cliente_activo_muestra_solo_sus_metricas_y_el_aviso(): void
    {
        $a = Cliente::factory()->create(['empresa' => 'Empresa Alfa']);
        $b = Cliente::factory()->create(['empresa' => 'Empresa Beta']);
        $this->metrica($a, 'Metrica-Alfa');
        $this->metrica($b, 'Metrica-Beta');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.metricas.index'))
            ->assertOk()
            ->assertSee('Metrica-Alfa')
            ->assertDontSee('Metrica-Beta')
            ->assertSee('Ver todos los clientes')
            ->assertDontSee('Selecciona un cliente');
    }

    public function test_cliente_explicito_en_la_url_manda_y_no_muestra_aviso(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->metrica($a, 'Metrica-Alfa');
        $this->metrica($b, 'Metrica-Beta');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.metricas.index', ['cliente' => $b->id]))
            ->assertOk()
            ->assertSee('Metrica-Beta')
            ->assertDontSee('Ver todos los clientes')
            ->assertSee('Selecciona un cliente');
    }

    public function test_sin_cliente_activo_todo_igual_que_antes(): void
    {
        $a = Cliente::factory()->create();
        $this->metrica($a, 'Metrica-Alfa');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.metricas.index'))
            ->assertOk()
            ->assertSee('Selecciona un cliente')
            ->assertDontSee('Metrica-Alfa')
            ->assertDontSee('Ver todos los clientes');
    }
}
