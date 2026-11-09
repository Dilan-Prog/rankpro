<?php

namespace Tests\Feature\Admin;

use App\Enums\TipoCobro;
use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\User;
use App\Support\FinanzasMetrics;
use App\Support\ResumenAgencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tipo de cobro de los servicios: solo el cobro mensual activo es MRR. Cubre la fuente única
 * (FinanzasMetrics), que todas las pantallas den la misma cifra, "Ingresos por proyecto",
 * el formulario/validación y la salida de Servicios.
 */
class TipoCobroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Base de ejemplo. Cliente A: mensual 1500, mensual 2265.22 con fecha_fin vencida (sigue en el MRR),
     * mensual pausado, mensual cancelado, único 7000, gratuito. Cliente B: mensual 2500 y único 8000.
     * Todo de tipo SEO para que la fila del módulo SEO (que solo mira servicios SEO) sea comparable.
     * MRR esperado: 1500 + 2265.22 + 2500 = 6265.22.
     *
     * @return array{0: Cliente, 1: Cliente}
     */
    private function baseDeEjemplo(): array
    {
        $a = Cliente::factory()->create(['estado' => 'activo', 'fecha_renovacion_contrato' => now()->addDays(20)]);
        $b = Cliente::factory()->create(['estado' => 'activo']);
        $base = ['tipo' => 'seo', 'estado' => 'activo'];

        Servicio::factory()->create($base + ['cliente_id' => $a->id, 'nombre' => 'Mensual 1', 'precio_mensual' => 1500]);
        Servicio::factory()->create($base + ['cliente_id' => $a->id, 'nombre' => 'Mensual vencido', 'precio_mensual' => 2265.22, 'fecha_inicio' => now()->subYear(), 'fecha_fin' => now()->subMonth()]);
        Servicio::factory()->create(['cliente_id' => $a->id, 'tipo' => 'seo', 'estado' => 'pausado', 'precio_mensual' => 999]);
        Servicio::factory()->create(['cliente_id' => $a->id, 'tipo' => 'seo', 'estado' => 'cancelado', 'precio_mensual' => 888]);
        Servicio::factory()->unico()->create($base + ['cliente_id' => $a->id, 'nombre' => 'Página web', 'precio_mensual' => 7000]);
        Servicio::factory()->gratuito()->create($base + ['cliente_id' => $a->id, 'nombre' => 'SEO gratis']);
        Servicio::factory()->create($base + ['cliente_id' => $b->id, 'nombre' => 'Mensual B', 'precio_mensual' => 2500]);
        Servicio::factory()->unico()->create($base + ['cliente_id' => $b->id, 'nombre' => 'Landing', 'precio_mensual' => 8000]);

        return [$a, $b];
    }

    // ---- Fuente única -----------------------------------------------------------------------

    public function test_el_mrr_solo_suma_cobro_mensual_activo(): void
    {
        $this->baseDeEjemplo();

        $this->assertSame(6265.22, round(FinanzasMetrics::mrr(), 2));
    }

    public function test_el_scope_y_el_espejo_en_memoria_coinciden(): void
    {
        $this->baseDeEjemplo();

        $porScope = Servicio::mensual()->pluck('id')->sort()->values()->all();
        $enMemoria = Servicio::all()->filter(fn (Servicio $s) => $s->aportaMrr())->pluck('id')->sort()->values()->all();

        $this->assertSame($porScope, $enMemoria);
        $this->assertCount(3, $porScope);
    }

    public function test_ingresos_por_proyecto_suma_unicos_no_cancelados_y_no_toca_el_mrr(): void
    {
        [$a] = $this->baseDeEjemplo();
        Servicio::factory()->unico()->create(['cliente_id' => $a->id, 'estado' => 'pausado', 'precio_mensual' => 1000]);
        Servicio::factory()->unico()->create(['cliente_id' => $a->id, 'estado' => 'cancelado', 'precio_mensual' => 50000]);

        $proyectos = FinanzasMetrics::ingresosPorProyecto();

        // 7000 + 8000 + 1000 (pausado cuenta); el cancelado de 50000 no.
        $this->assertSame(16000.0, $proyectos['total']);
        $this->assertSame(3, $proyectos['proyectos']);
        $this->assertSame(6265.22, round(FinanzasMetrics::mrr(), 2));
    }

    public function test_todas_las_pantallas_dan_el_mismo_mrr(): void
    {
        [$a, $b] = $this->baseDeEjemplo();
        foreach ([$a, $b] as $cliente) {
            AdsCampana::factory()->create(['cliente_id' => $cliente->id, 'servicio_id' => Servicio::where('cliente_id', $cliente->id)->value('id')]);
        }
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $esperado = 6265.22;
        $esperadoA = 3765.22;

        // Finanzas
        $finanzas = $this->get(route('admin.finanzas.index'))->assertOk();
        $this->assertSame($esperado, round($finanzas->viewData('mrr'), 2));
        $this->assertSame($esperado, round((float) $finanzas->viewData('mrrPorCliente')->sum('mrr'), 2));

        // Dashboard: tarjeta del KPI (valor compacto de ResumenAgencia) y MRR por contrato por vencer.
        $dashboard = $this->get(route('admin.dashboard'))->assertOk();
        // Regla del KPI: menos de 10,000 completo ("$6,265"); desde 10,000 en miles con un decimal.
        $this->assertSame(
            '$' . ($esperado < 10000 ? number_format($esperado, 0) : rtrim(rtrim(number_format($esperado / 1000, 1), '0'), '.') . 'K'),
            $dashboard->viewData('kpis')[0]['value']
        );
        $this->assertSame($esperadoA, round($dashboard->viewData('contractsExpiring')[0]['mrr'], 2));

        // Inicio comparte ResumenAgencia con el Dashboard.
        $this->assertSame($dashboard->viewData('kpis')[0]['value'], ResumenAgencia::kpis(now())[0]['value']);
        $inicio = $this->get(route('admin.inicio', ['tab' => 'resumen']))->assertOk();
        $this->assertSame($dashboard->viewData('kpis')[0]['value'], $inicio->viewData('kpis')[0]['value']);

        // Ads (suma sobre las cuentas con campañas)
        $cuentas = $this->get(route('admin.ads.index'))->assertOk()->viewData('cuentasInfo');
        $this->assertSame($esperado, round(collect($cuentas)->sum('mrr'), 2));
        $this->assertSame($esperadoA, round($cuentas[$a->id]['mrr'], 2));

        // SEO
        $seo = $this->get(route('admin.seo.index'))->assertOk()->viewData('clientes');
        $this->assertSame($esperado, round($seo->sum('mrr'), 2));

        // CRM
        $crm = $this->get(route('admin.clientes.index'))->assertOk()->viewData('clientes');
        $this->assertSame($esperado, round($crm->sum('mrr'), 2));
        $this->assertSame($esperadoA, round($crm->firstWhere('id', $a->id)['mrr'], 2));

        // API v1
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $this->getJson('/api/v1/dashboard/resumen')->assertOk()
            ->assertJsonPath('data.mrr', $esperado)
            ->assertJsonPath('data.ingresos_por_proyecto', 15000);
        $this->getJson('/api/v1/finanzas/resumen')->assertOk()->assertJsonPath('data.mrr', $esperado);
    }

    // ---- Ingresos por proyecto en pantalla -------------------------------------------------------

    public function test_finanzas_y_dashboard_muestran_ingresos_por_proyecto_aparte_del_mrr(): void
    {
        $this->baseDeEjemplo();
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $finanzas = $this->get(route('admin.finanzas.index'))->assertOk();
        $finanzas->assertSee('Ingresos por proyecto');
        $this->assertSame(15000.0, $finanzas->viewData('ingresosProyecto')['total']);

        $dashboard = $this->get(route('admin.dashboard'))->assertOk();
        $dashboard->assertSee('Ingresos por proyecto');
        $this->assertSame(15000.0, $dashboard->viewData('ingresosProyecto')['total']);
        // Los KPIs compartidos con Inicio siguen siendo 4: el dato nuevo no entra al MRR.
        $this->assertCount(4, $dashboard->viewData('kpis'));
    }

    // ---- Servicios: alta, validación y salida ---------------------------------------------------

    public function test_servicios_guarda_el_tipo_de_cobro_y_un_gratuito_queda_en_cero(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $cliente = Cliente::factory()->create();

        $respuesta = $this->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'SEO de cortesía',
            'tipo_cobro' => 'gratuito', 'precio_mensual' => 5000, 'estado' => 'activo',
        ])->assertCreated();

        $respuesta->assertJsonPath('tipo_cobro', 'gratuito')->assertJsonPath('precio_mensual', 0);
        $this->assertDatabaseHas('servicios', ['nombre' => 'SEO de cortesía', 'tipo_cobro' => 'gratuito', 'precio_mensual' => 0]);

        // Gratuito puede ir sin precio; los demás lo exigen.
        $this->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'Otro gratis', 'tipo_cobro' => 'gratuito', 'estado' => 'activo',
        ])->assertCreated();
        $this->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'Sin precio', 'tipo_cobro' => 'unico', 'estado' => 'activo',
        ])->assertStatus(422)->assertJsonValidationErrors('precio_mensual');
        $this->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'Mal tipo', 'tipo_cobro' => 'semanal', 'precio_mensual' => 1, 'estado' => 'activo',
        ])->assertStatus(422)->assertJsonValidationErrors('tipo_cobro');
    }

    public function test_sin_tipo_de_cobro_el_servicio_nace_mensual(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $cliente = Cliente::factory()->create();

        $this->postJson(route('admin.servicios.store'), [
            'cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'Compatibilidad', 'precio_mensual' => 3000, 'estado' => 'activo',
        ])->assertCreated()->assertJsonPath('tipo_cobro', 'mensual');
    }

    public function test_to_row_solo_anualiza_y_acumula_el_cobro_mensual_y_marca_vencidos(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $inicio = now()->subMonths(4);
        $mensual = Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'M', 'precio_mensual' => 1000, 'fecha_inicio' => $inicio, 'fecha_fin' => now()->subDays(3)]);
        $unico = Servicio::factory()->unico()->create(['cliente_id' => $cliente->id, 'nombre' => 'U', 'precio_mensual' => 7000, 'fecha_inicio' => $inicio]);
        $gratis = Servicio::factory()->gratuito()->create(['cliente_id' => $cliente->id, 'nombre' => 'G', 'fecha_inicio' => $inicio]);
        $pausadoVencido = Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'P', 'estado' => 'pausado', 'fecha_inicio' => $inicio, 'fecha_fin' => now()->subDays(3)]);

        $filas = collect($this->get(route('admin.servicios.index'))->assertOk()->viewData('servicios'))->keyBy('id');

        $this->assertSame(12000.0, $filas[$mensual->id]['anualizado']);
        $this->assertGreaterThan(0, $filas[$mensual->id]['ingreso_acumulado']);
        $this->assertTrue($filas[$mensual->id]['vencido']);
        $this->assertTrue($filas[$mensual->id]['aporta_mrr']);

        foreach ([$unico, $gratis] as $s) {
            $this->assertNull($filas[$s->id]['anualizado']);
            $this->assertNull($filas[$s->id]['ingreso_acumulado']);
            $this->assertFalse($filas[$s->id]['aporta_mrr']);
            $this->assertFalse($filas[$s->id]['vencido']);
        }
        $this->assertSame('unico', $filas[$unico->id]['tipo_cobro']);
        // Vencido es solo para servicios que siguen activos.
        $this->assertFalse($filas[$pausadoVencido->id]['vencido']);
    }

    public function test_la_lista_de_servicios_muestra_el_badge_vencido_y_el_precio_segun_el_cobro(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs(User::factory()->create(['is_active' => true]));
        Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'Renovar ya', 'precio_mensual' => 1500, 'fecha_inicio' => now()->subYear(), 'fecha_fin' => now()->subDays(2)]);
        Servicio::factory()->unico()->create(['cliente_id' => $cliente->id, 'nombre' => 'Proyecto web', 'precio_mensual' => 7000]);
        Servicio::factory()->gratuito()->create(['cliente_id' => $cliente->id, 'nombre' => 'Cortesía']);

        $html = $this->get(route('admin.servicios.index'))->assertOk()->getContent();

        $this->assertStringContainsString('badge--danger', $html);
        $this->assertStringContainsString('>Vencido<', $html);
        $this->assertStringContainsString('/mes', $html);
        $this->assertStringContainsString('pago único', $html);
        $this->assertStringContainsString('Gratuito', $html);
        $this->assertStringContainsString('id="tipo_cobro"', $html);
    }

    public function test_la_api_acepta_y_devuelve_tipo_cobro_y_filtra_por_el(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $cliente = Cliente::factory()->create();

        $id = $this->postJson('/api/v1/servicios', [
            'cliente_id' => $cliente->id, 'tipo' => 'rediseno', 'nombre' => 'Web', 'tipo_cobro' => 'unico', 'precio_mensual' => 9000, 'estado' => 'activo',
        ])->assertCreated()->assertJsonPath('data.tipo_cobro', 'unico')->json('data.id');
        Servicio::factory()->create(['cliente_id' => $cliente->id]);

        $this->getJson('/api/v1/servicios?tipo_cobro=unico')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        $this->putJson("/api/v1/servicios/{$id}", [
            'cliente_id' => $cliente->id, 'tipo' => 'rediseno', 'nombre' => 'Web', 'tipo_cobro' => 'mensual', 'precio_mensual' => 9000, 'estado' => 'activo',
        ])->assertOk()->assertJsonPath('data.tipo_cobro', 'mensual');
        $this->assertDatabaseHas('servicio_eventos', ['servicio_id' => $id, 'descripcion' => 'Tipo de cobro cambiado a Mensual.']);
    }

    // ---- Documentos -----------------------------------------------------------------------------

    public function test_el_contrato_suma_solo_mensuales_y_lista_el_pago_unico_aparte(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $cliente = Cliente::factory()->create();
        $mensual = Servicio::factory()->create(['cliente_id' => $cliente->id, 'precio_mensual' => 1500]);
        $unico = Servicio::factory()->unico()->create(['cliente_id' => $cliente->id, 'precio_mensual' => 7000]);
        $gratis = Servicio::factory()->gratuito()->create(['cliente_id' => $cliente->id]);

        $respuesta = $this->post(route('admin.archivos.contratos.preview'), [
            'cliente_id' => $cliente->id,
            'servicios' => [$mensual->id, $unico->id, $gratis->id],
            'fecha_inicio' => now()->toDateString(),
            'condiciones' => 'Condiciones de prueba',
        ])->assertOk();

        $html = $respuesta->viewData('documentoHtml');
        $this->assertStringContainsString('(pago único)', $html);
        $this->assertStringContainsString('Sin costo', $html);
        $this->assertStringContainsString('Pago único (no es mensualidad)', $html);
        $this->assertStringContainsString('7,000.00', $html);
        // Total mensual = solo el servicio mensual (1,500.00), no 8,500.00.
        $this->assertMatchesRegularExpression('/Total mensual[^<]*<\/td><td[^>]*>\$1,500\.00 MXN/', $html);
    }

    public function test_el_enum_tiene_etiquetas(): void
    {
        $this->assertSame('Pago único', TipoCobro::Unico->label());
        $this->assertSame(['mensual', 'unico', 'gratuito'], array_column(TipoCobro::cases(), 'value'));
    }
}
