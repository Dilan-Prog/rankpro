<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Models\User;
use App\Support\Finanzas\Conciliacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Reproduce en miniatura los datos reales de noviembre de 2026 (Hotel Fratelli): servicios mensuales con
 * fechas de inicio/fin distintas, dos filas idénticas de agosto y un pendiente de septiembre sin servicio.
 */
class ConciliacionTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private Servicio $hosting;

    private Servicio $ads;

    private Servicio $inversion;

    private Servicio $web;

    protected function setUp(): void
    {
        parent::setUp();

        // Hora fija: la regla de «creadas en el mismo minuto» no puede depender de cuándo corre la suite.
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 10, 0, 0));

        $this->cliente = Cliente::factory()->create(['nombre' => 'Contacto', 'empresa' => 'Hotel Fratelli']);

        $this->hosting = Servicio::factory()->create(['cliente_id' => $this->cliente->id, 'nombre' => 'Dominio + Hosting', 'precio_mensual' => 2265.22, 'fecha_inicio' => '2026-07-01', 'fecha_fin' => '2029-05-01']);
        $this->ads = Servicio::factory()->create(['cliente_id' => $this->cliente->id, 'nombre' => 'Google Ads', 'precio_mensual' => 1500, 'fecha_inicio' => '2026-08-12', 'fecha_fin' => '2026-09-12']);
        $this->inversion = Servicio::factory()->create(['cliente_id' => $this->cliente->id, 'nombre' => 'Inversion Ads', 'precio_mensual' => 2500, 'fecha_inicio' => '2026-08-12', 'fecha_fin' => '2026-09-12']);
        $this->web = Servicio::factory()->unico()->create(['cliente_id' => $this->cliente->id, 'nombre' => 'Pagina Web', 'precio_mensual' => 7000, 'fecha_inicio' => '2026-07-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ingreso(array $o = []): Finanza
    {
        return Finanza::create(array_merge([
            'cliente_id' => $this->cliente->id,
            'servicio_id' => null,
            'concepto' => 'Cargo',
            'tipo' => 'ingreso',
            'monto' => 1000,
            'estado' => 'pagado',
            'fecha_emision' => '2026-08-01',
            'fecha_pago' => null,
            'mes' => 8,
            'anio' => 2026,
        ], $o));
    }

    /** Datos reales: julio completo, agosto con dos filas de Ads sin servicio, septiembre pendiente. */
    private function datosReales(): void
    {
        $this->ingreso(['concepto' => 'Pagina web', 'monto' => 7000, 'mes' => 7, 'servicio_id' => $this->web->id]);
        $this->ingreso(['concepto' => 'Hostinger', 'monto' => 2265.22, 'mes' => 7, 'servicio_id' => $this->hosting->id]);
        $this->ingreso(['concepto' => 'Gestión de Ads', 'monto' => 1500, 'mes' => 8]);
        $this->ingreso(['concepto' => 'Gestión de Ads', 'monto' => 1500, 'mes' => 8]);

        // La de septiembre se capturó otro día: solo las de agosto nacieron en el mismo segundo.
        Carbon::setTestNow(now()->addDays(30));
        $this->ingreso(['concepto' => 'Gestión de Ads', 'monto' => 1500, 'mes' => 9, 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-10-12']);
        Carbon::setTestNow(Carbon::create(2026, 11, 8, 10, 0, 0));
    }

    private function mes(string $periodo, ?int $clienteId = null): array
    {
        $meses = (new Conciliacion(now(), $clienteId))->meses();

        return collect($meses)->firstWhere('periodo', $periodo);
    }

    public function test_el_esperado_respeta_fecha_inicio_y_fecha_fin_pero_el_mes_en_curso_sigue_la_regla_del_mrr(): void
    {
        $meses = (new Conciliacion(now()))->meses();

        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_column($meses, 'periodo'));

        $esperado = array_column($meses, 'esperado', 'periodo');
        $this->assertSame(0.0, $esperado['2026-06']);       // antes de fecha_inicio de todo
        $this->assertSame(2265.22, $esperado['2026-07']);   // solo hosting; Ads empieza el 12 de agosto
        $this->assertSame(6265.22, $esperado['2026-08']);   // Ads entra a mitad de mes y ya cuenta
        $this->assertSame(6265.22, $esperado['2026-09']);   // fecha_fin el 12 de septiembre: aún cuenta ese mes
        $this->assertSame(2265.22, $esperado['2026-10']);   // meses cerrados: Ads ya terminó
        $this->assertSame(6265.22, $esperado['2026-11']);   // mes en curso = MRR actual, aunque Ads tenga fecha_fin vencida

        // Los pagos únicos nunca son esperado mensual (7,000 de la web no aparece).
        $actual = collect($meses)->last();
        $this->assertSame(4000.0, $actual['incluye_vencidos']);
        $this->assertSame(2265.22, $actual['esperado_por_fecha_fin']);
        $this->assertSame(0.0, $meses[2]['incluye_vencidos']);
    }

    public function test_el_esperado_del_mes_en_curso_coincide_con_el_mrr(): void
    {
        $this->assertSame(\App\Support\FinanzasMetrics::mrr(), $this->mes('2026-11')['esperado']);
    }

    public function test_agosto_muestra_la_brecha_de_facturar_con_los_datos_reales(): void
    {
        $this->datosReales();

        $agosto = $this->mes('2026-08');

        $this->assertSame(6265.22, $agosto['esperado']);
        $this->assertSame(3000.0, $agosto['facturado']);
        $this->assertSame(3000.0, $agosto['cobrado']);
        $this->assertSame(0.0, $agosto['por_cobrar']);
        $this->assertSame(3265.22, $agosto['falta_facturar']);
        $this->assertSame('falta_facturar', $agosto['estado']['clave']);
        $this->assertSame(['Falta facturar'], array_column($agosto['estado']['etiquetas'], 'texto'));
    }

    public function test_septiembre_falta_facturar_y_falta_cobrar_a_la_vez(): void
    {
        $this->datosReales();

        $sep = $this->mes('2026-09');

        $this->assertSame(1500.0, $sep['facturado']);
        $this->assertSame(0.0, $sep['cobrado']);
        $this->assertSame(1500.0, $sep['por_cobrar']);
        $this->assertSame(4765.22, $sep['falta_facturar']);
        $this->assertSame(['Falta facturar', 'Falta cobrar'], array_column($sep['estado']['etiquetas'], 'texto'));
    }

    public function test_julio_cuadra_y_lo_facturado_de_mas_se_muestra_como_sobre_esperado_no_como_error(): void
    {
        $this->datosReales();

        $julio = $this->mes('2026-07');

        $this->assertSame(9265.22, $julio['facturado']);
        $this->assertSame(7000.0, $julio['sobre_esperado']);
        $this->assertSame(0.0, $julio['falta_facturar']);
        $this->assertSame('cuadra', $julio['estado']['clave']);
    }

    public function test_un_mes_sin_esperado_ni_facturas_es_sin_movimiento_y_octubre_sin_filas_falta_facturar(): void
    {
        $this->datosReales();

        $this->assertSame('sin_movimiento', $this->mes('2026-06')['estado']['clave']);

        $octubre = $this->mes('2026-10');
        $this->assertSame(0.0, $octubre['facturado']);
        $this->assertSame(2265.22, $octubre['falta_facturar']);
    }

    public function test_el_detalle_dice_que_servicio_tiene_fila_ese_mes_y_separa_las_filas_sin_servicio(): void
    {
        $this->datosReales();

        $julio = $this->mes('2026-07')['clientes'][0];
        $this->assertSame('Hotel Fratelli', $julio['cliente']);
        $hosting = collect($julio['servicios'])->firstWhere('servicio_id', $this->hosting->id);
        $this->assertSame('pagado', $hosting['estado']);
        $this->assertSame(2265.22, $hosting['facturado']);
        $this->assertSame(7000.0, $julio['otros_servicios']['monto']); // la web (pago único) con servicio_id

        $agosto = $this->mes('2026-08')['clientes'][0];
        $this->assertSame(['sin_fila', 'sin_fila', 'sin_fila'], array_column($agosto['servicios'], 'estado'));
        $this->assertSame(['filas' => 2, 'monto' => 3000.0], $agosto['sin_servicio']);

        $sep = $this->mes('2026-09')['clientes'][0];
        $this->assertSame(1500.0, $sep['por_cobrar']);
    }

    public function test_un_servicio_con_fila_pendiente_se_marca_facturado_sin_cobrar(): void
    {
        $this->ingreso(['concepto' => 'Ads', 'monto' => 1500, 'mes' => 8, 'estado' => 'pendiente', 'servicio_id' => $this->ads->id]);

        $agosto = $this->mes('2026-08')['clientes'][0];
        $ads = collect($agosto['servicios'])->firstWhere('servicio_id', $this->ads->id);

        $this->assertSame('pendiente', $ads['estado']);
        $this->assertSame(1500.0, $ads['facturado']);
    }

    public function test_con_cliente_activo_solo_cuenta_ese_cliente(): void
    {
        $this->datosReales();
        $otro = Cliente::factory()->create();
        Servicio::factory()->create(['cliente_id' => $otro->id, 'precio_mensual' => 10000, 'fecha_inicio' => '2026-01-01']);
        $this->ingreso(['cliente_id' => $otro->id, 'monto' => 555, 'mes' => 8]);

        $todos = $this->mes('2026-08');
        $soloUno = $this->mes('2026-08', $this->cliente->id);

        $this->assertSame(16265.22, $todos['esperado']);
        $this->assertSame(3555.0, $todos['facturado']);
        $this->assertSame(6265.22, $soloUno['esperado']);
        $this->assertSame(3000.0, $soloUno['facturado']);
        $this->assertCount(1, $soloUno['clientes']);
    }

    public function test_marca_posibles_duplicados_e_inconsistencias_sin_tocar_nada(): void
    {
        $this->datosReales();
        $dup1 = Finanza::where('mes', 8)->orderBy('id')->first();
        $dup2 = Finanza::where('mes', 8)->orderByDesc('id')->first();
        $gasto = $this->ingreso(['tipo' => 'gasto', 'concepto' => 'Hosting', 'monto' => 2000, 'mes' => 7, 'estado' => 'pendiente', 'fecha_pago' => '2026-07-20']);
        $cero = $this->ingreso(['concepto' => 'SEO Cobrado', 'monto' => 0, 'mes' => 7, 'servicio_id' => $this->hosting->id]);

        $antes = Finanza::orderBy('id')->get(['id', 'concepto', 'monto', 'estado', 'fecha_pago', 'updated_at'])->toArray();

        $revision = (new Conciliacion(now(), null))->revision();

        $this->assertCount(1, $revision['duplicados']);
        $this->assertSame([$dup1->id, $dup2->id], array_column($revision['duplicados'][0]['filas'], 'id'));
        $this->assertSame(1500.0, $revision['duplicados'][0]['monto_sobrante']);
        $this->assertStringContainsString('mes', $revision['duplicados'][0]['motivo']);
        $this->assertStringContainsString('admin/finanzas?editar=' . $dup1->id, $revision['duplicados'][0]['filas'][0]['editar_url']);

        $porClave = collect($revision['inconsistencias'])->keyBy('clave');
        $this->assertSame([$gasto->id], array_column($porClave['pendiente_con_fecha_pago']['filas'], 'id'));
        $this->assertSame([$cero->id], array_column($porClave['ingreso_en_cero']['filas'], 'id'));
        // Sin servicio: las 2 de agosto + la de septiembre (el gasto no es ingreso).
        $this->assertCount(3, $porClave['ingreso_sin_servicio']['filas']);

        // Solo lectura: ni filas nuevas, ni borradas, ni modificadas.
        $despues = Finanza::orderBy('id')->get(['id', 'concepto', 'monto', 'estado', 'fecha_pago', 'updated_at'])->toArray();
        $this->assertSame($antes, $despues);
    }

    public function test_dos_ingresos_distintos_no_se_marcan_como_duplicado(): void
    {
        $this->ingreso(['concepto' => 'Ads', 'monto' => 1500, 'mes' => 8]);
        Carbon::setTestNow(now()->addMinutes(5));
        $this->ingreso(['concepto' => 'Ads', 'monto' => 1500, 'mes' => 9]); // otro mes y otro minuto
        $this->ingreso(['concepto' => 'Ads', 'monto' => 1600, 'mes' => 8]); // otro monto

        $this->assertSame([], (new Conciliacion(now(), null))->revision()['duplicados']);
    }

    public function test_filas_identicas_creadas_en_el_mismo_minuto_de_distinto_mes_son_posible_duplicado(): void
    {
        $this->ingreso(['concepto' => 'Ads', 'monto' => 1500, 'mes' => 8]);
        $this->ingreso(['concepto' => 'ads ', 'monto' => 1500, 'mes' => 9, 'estado' => 'pendiente']);

        $dup = (new Conciliacion(now(), null))->revision()['duplicados'];

        $this->assertCount(1, $dup);
        $this->assertStringContainsString('mismo minuto', $dup[0]['motivo']);
    }

    public function test_el_panel_muestra_las_cifras_de_agosto_los_duplicados_y_el_enlace_de_edicion(): void
    {
        $this->datosReales();
        $ids = Finanza::where('mes', 8)->orderBy('id')->pluck('id');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.finanzas.index'))
            ->assertOk()
            ->assertSee('data-panel="conciliacion"', false)
            ->assertSee('Agosto 2026')
            ->assertSee('$6,265.22')
            ->assertSee('$3,000.00')
            ->assertSee('$3,265.22')
            ->assertSee('Falta facturar')
            ->assertSee('Posibles duplicados y datos a revisar')
            ->assertSee('Hotel Fratelli')
            ->assertSee('?editar=' . $ids[0], false)
            ->assertSee('?editar=' . $ids[1], false);
    }

    public function test_el_panel_respeta_el_cliente_activo(): void
    {
        $this->datosReales();
        $otro = Cliente::factory()->create(['empresa' => 'Hotel Ajeno']);
        Servicio::factory()->create(['cliente_id' => $otro->id, 'precio_mensual' => 9999, 'fecha_inicio' => '2026-01-01']);

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['cliente_activo_id' => $this->cliente->id])
            ->get(route('admin.finanzas.index'))
            ->assertOk()
            ->assertSee('$6,265.22');

        $this->assertSame(6265.22, collect($respuesta->viewData('conciliacionMeses'))->firstWhere('periodo', '2026-08')['esperado']);
    }

    public function test_el_panel_funciona_sin_ningun_dato(): void
    {
        Servicio::query()->forceDelete();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.finanzas.index'))
            ->assertOk()
            ->assertSee('No se encontró nada que revisar.');
    }

    public function test_el_kpi_por_cobrar_incluye_el_vencido_de_meses_anteriores_y_lo_muestra_aparte(): void
    {
        $this->datosReales(); // septiembre pendiente 1,500; noviembre sin filas

        $r = $this->actingAs(User::factory()->create())->get(route('admin.finanzas.index'))->assertOk();

        $this->assertSame(1500.0, $r->viewData('vencidoAnterior'));
        $this->assertSame(1, $r->viewData('facturasVencidasAnterior'));
        $this->assertSame(1500.0, $r->viewData('pendiente'));
        $this->assertSame(0.0, $r->viewData('pendienteMes'));
        $this->assertSame(0.0, $r->viewData('cobrado'));
        $r->assertSee('Vencido de meses anteriores')->assertSee('Cobrado del mes');
    }
}
