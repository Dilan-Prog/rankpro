<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use App\Models\Servicio;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\SeoPlan\VistasActividades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vistas de solo lectura del SEO Plan (línea de tiempo y calendario): la
 * geometría vive en VistasActividades y se prueba sin Blade; la pantalla solo
 * debe pintarla y no mezclar actividades de otro cliente.
 */
class SeoPlanVistasTest extends TestCase
{
    use RefreshDatabase;

    private function campana(Cliente $cliente): SeoCampana
    {
        return SeoCampana::create([
            'cliente_id' => $cliente->id,
            'servicio_id' => Servicio::factory()->create()->id,
            'nombre' => 'Campaña SEO',
            'url_sitio' => 'https://ejemplo.com',
            'estado' => 'activa',
            'fase_actual' => 'auditoria',
            'ciclo_actual' => 1,
            'fecha_inicio' => now()->subMonth(),
        ]);
    }

    private function actividad(SeoCampana $campana, array $extra = []): SeoOnPageAccion
    {
        return SeoOnPageAccion::create($extra + [
            'seo_campana_id' => $campana->id,
            'tipo' => 'on_page',
            'url_pagina' => '',
            'accion' => 'Optimizar títulos',
            'estado' => 'pendiente',
        ]);
    }

    public function test_linea_de_tiempo_separa_las_actividades_sin_fecha_limite(): void
    {
        $campana = $this->campana(Cliente::factory()->create());
        $con = $this->actividad($campana, ['fecha' => '2026-11-03', 'fecha_limite' => '2026-11-12']);
        $sin = $this->actividad($campana, ['accion' => 'Sin fecha']);

        $lt = VistasActividades::lineaTiempo([$con, $sin], Carbon::parse('2026-11-05'));

        $this->assertCount(1, $lt['barras']);
        $this->assertSame([$sin->id], array_map(fn ($a) => $a->id, $lt['sin_fecha']));
        // Rango = noviembre completo (30 días).
        $this->assertSame(30 * VistasActividades::PX_DIA, $lt['ancho']);
        // Del 3 al 12 inclusive = 10 días; empieza el día 3 (desfase de 2 días).
        $this->assertSame(10 * VistasActividades::PX_DIA, $lt['barras'][0]['width']);
        $this->assertSame(2 * VistasActividades::PX_DIA, $lt['barras'][0]['left']);
        $this->assertSame(4 * VistasActividades::PX_DIA + intdiv(VistasActividades::PX_DIA, 2), $lt['hoy_left']);
        $this->assertSame('nov 2026', $lt['meses'][0]['etiqueta']);
        $this->assertNotEmpty($lt['semanas']);
    }

    public function test_linea_de_tiempo_sin_fecha_de_inicio_usa_la_creacion_e_invierte_rangos_al_reves(): void
    {
        $campana = $this->campana(Cliente::factory()->create());
        $creada = $this->actividad($campana, ['fecha_limite' => '2026-11-20']);
        $creada->forceFill(['created_at' => '2026-11-10 09:00:00'])->saveQuietly();
        $alReves = $this->actividad($campana, ['fecha' => '2026-11-25', 'fecha_limite' => '2026-11-22']);

        $lt = VistasActividades::lineaTiempo([$creada->fresh(), $alReves], Carbon::parse('2026-11-01'));

        $this->assertSame(11 * VistasActividades::PX_DIA, $lt['barras'][0]['width']); // 10 al 20
        $this->assertSame(9 * VistasActividades::PX_DIA, $lt['barras'][0]['left']);
        $this->assertSame(4 * VistasActividades::PX_DIA, $lt['barras'][1]['width']); // 22 al 25, sin ancho negativo
    }

    public function test_linea_de_tiempo_vacia_no_calcula_ancho(): void
    {
        $lt = VistasActividades::lineaTiempo([]);

        $this->assertSame([], $lt['barras']);
        $this->assertSame(0, $lt['ancho']);
        $this->assertNull($lt['hoy_left']);
    }

    public function test_calendario_cubre_los_meses_del_rango_y_coloca_cada_actividad_en_su_dia(): void
    {
        $campana = $this->campana(Cliente::factory()->create());
        $a = $this->actividad($campana, ['fecha_limite' => '2026-11-12']);
        $b = $this->actividad($campana, ['fecha_limite' => '2027-01-04']);
        $sin = $this->actividad($campana);

        $cal = VistasActividades::calendario([$a, $b, $sin], Carbon::parse('2026-11-02'));

        $this->assertSame(['2026-11', '2026-12', '2027-01'], array_column($cal['meses'], 'clave'));
        $this->assertSame(0, $cal['indice_hoy']);
        $this->assertSame([$sin->id], array_map(fn ($x) => $x->id, $cal['sin_fecha']));

        $dias = collect($cal['meses'][0]['semanas'])->flatten(1)->keyBy('fecha');
        $this->assertSame([$a->id], array_map(fn ($x) => $x->id, $dias['2026-11-12']['actividades']));
        $this->assertTrue($dias['2026-11-02']['hoy']);
        // Las semanas empiezan en lunes: 1 nov 2026 es domingo, así que la primera fila arranca el 26 oct.
        $this->assertSame('2026-10-26', $cal['meses'][0]['semanas'][0][0]['fecha']);
        $this->assertFalse($cal['meses'][0]['semanas'][0][0]['del_mes']);
    }

    public function test_calendario_incluye_el_mes_de_hoy_aunque_no_tenga_actividades(): void
    {
        $cal = VistasActividades::calendario([], Carbon::parse('2026-11-15'));

        $this->assertCount(1, $cal['meses']);
        $this->assertSame('Noviembre 2026', $cal['meses'][0]['etiqueta']);
    }

    public function test_la_pantalla_ofrece_las_cuatro_vistas_y_pinta_solo_actividades_del_cliente_activo(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $this->actividad($this->campana($cliente), ['accion' => 'Meta descripciones <b>', 'fecha_limite' => now()->addDays(5)->toDateString()]);
        $this->actividad($this->campana($cliente), ['accion' => 'Sin plazo']);
        $this->actividad($this->campana($otro), ['accion' => 'Actividad ajena', 'fecha_limite' => now()->addDays(3)->toDateString()]);

        $resp = $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $cliente->id])
            ->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('data-spl-vista="linea"', false)
            ->assertSee('data-spl-vista="calendario"', false)
            ->assertSee('data-spl-vista-panel="linea"', false)
            ->assertSee('data-spl-vista-panel="calendario"', false)
            ->assertSee('Sin fecha límite (no se dibujan)')
            ->assertSee('Sin fecha límite (no aparecen en el calendario)')
            ->assertSee('Meta descripciones &lt;b&gt;', false)
            ->assertDontSee('Actividad ajena')
            ->assertDontSee('Meta descripciones <b>', false);

        $this->assertSame(1, substr_count($resp->getContent(), 'class="spl-lt__barra '));
    }

    public function test_sin_fechas_limite_la_linea_de_tiempo_muestra_estado_vacio(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actividad($this->campana($cliente), ['accion' => 'Solo sin plazo']);

        $this->actingAs(User::factory()->create())
            ->withSession([ClienteActivo::CLAVE => $cliente->id])
            ->get(route('admin.seo-plan.index'))
            ->assertOk()
            ->assertSee('Ninguna actividad tiene fecha límite para dibujar la línea de tiempo.');
    }
}
