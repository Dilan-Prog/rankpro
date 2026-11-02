<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El índice SEO respeta el cliente activo del menú (grupo Marca): sin él muestra
 * todas las cuentas; con él, solo las campañas, contadores y KPIs de ese cliente.
 */
class SeoClienteActivoTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(string $nombre, string $campana): Cliente
    {
        $cliente = Cliente::factory()->create(['nombre' => $nombre]);
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo']);
        SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'nombre' => $campana,
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
        ]);

        return $cliente;
    }

    public function test_sin_cliente_activo_muestra_todas_las_cuentas(): void
    {
        $this->cuenta('Alfa Cliente', 'Campana Alfa');
        $this->cuenta('Beta Cliente', 'Campana Beta');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.seo.index'))
            ->assertOk()
            ->assertSee('Campana Alfa')
            ->assertSee('Campana Beta')
            ->assertSee('2 cuentas con SEO')
            ->assertDontSee('Mostrando solo a');
    }

    public function test_con_cliente_activo_solo_muestra_sus_campanas_y_contadores(): void
    {
        $alfa = $this->cuenta('Alfa Cliente', 'Campana Alfa');
        $this->cuenta('Beta Cliente', 'Campana Beta');

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $alfa->id])
            ->get(route('admin.seo.index'))
            ->assertOk()
            ->assertSee('Mostrando solo a')
            ->assertSee('Campana Alfa')
            ->assertDontSee('Campana Beta')
            ->assertSee('1 cuenta con SEO')
            ->assertViewHas('clientesConSeo', 1)
            ->assertViewHas('campanasActivas', 1);
    }

    public function test_cliente_activo_sin_servicio_seo_muestra_vacio(): void
    {
        $this->cuenta('Alfa Cliente', 'Campana Alfa');
        $sinSeo = Cliente::factory()->create(['nombre' => 'Sin Seo']);

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $sinSeo->id])
            ->get(route('admin.seo.index'))
            ->assertOk()
            ->assertDontSee('Campana Alfa')
            ->assertViewHas('clientesConSeo', 0);
    }

    public function test_el_modal_preselecciona_el_cliente_activo(): void
    {
        $alfa = $this->cuenta('Alfa Cliente', 'Campana Alfa');

        $html = $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $alfa->id])
            ->get(route('admin.seo.index'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$alfa->id.'"\s+selected/', $html);
    }

    public function test_el_detalle_no_se_filtra_por_cliente_activo(): void
    {
        $alfa = $this->cuenta('Alfa Cliente', 'Campana Alfa');
        $beta = $this->cuenta('Beta Cliente', 'Campana Beta');
        $campanaBeta = SeoCampana::where('cliente_id', $beta->id)->first();
        // El detalle espera las filas de fase del ciclo 1 (las crea store() al dar de alta la campaña).
        $campanaBeta->auditorias()->create(['ciclo' => 1, 'checklist' => []]);
        $campanaBeta->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
        $campanaBeta->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
        $campanaBeta->reportes()->create(['ciclo' => 1, 'checklist' => []]);

        $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $alfa->id])
            ->get(route('admin.seo.show', $campanaBeta->id))
            ->assertOk();
    }
}
