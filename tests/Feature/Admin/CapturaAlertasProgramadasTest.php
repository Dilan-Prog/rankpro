<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\User;
use App\Support\Captura\AlertasProgramadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «Reportes por salir»: con 3 días de anticipación, qué le falta a cada envío programado
 * (reporte del periodo anterior y celdas de captura del mes anterior). Solo avisa.
 */
class CapturaAlertasProgramadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Hoy es 5 de noviembre: lo que sale es el reporte de octubre.
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function programacion(?Cliente $cliente = null, string $envio = '2026-11-08 09:00:00', array $extra = []): ReporteProgramacion
    {
        return ReporteProgramacion::factory()->create($extra + [
            'cliente_id' => ($cliente ?? Cliente::factory()->create(['estado' => 'activo']))->id,
            'proximo_envio_en' => $envio,
        ]);
    }

    private function reporte(Cliente $cliente, string $estado = 'listo', array $extra = []): Reporte
    {
        return Reporte::create($extra + [
            'cliente_id' => $cliente->id, 'area' => AreaReporte::Seo->value, 'titulo' => 'Octubre',
            'periodo_inicio' => '2026-10-01', 'periodo_fin' => '2026-10-31', 'estado' => $estado,
        ]);
    }

    private function alertas(?int $clienteId = null): array
    {
        return app(AlertasProgramadas::class)->calcular(now(), $clienteId);
    }

    private function listaDeKeywords(Cliente $cliente): KeywordLista
    {
        $lista = KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => 'Lista', 'canal' => 'seo', 'estado' => 'en_uso']);
        $lista->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
        Keyword::create(['cliente_id' => $cliente->id, 'lista_id' => $lista->id, 'keyword' => 'hotel', 'tipo' => 'principal', 'estado' => 'en_uso']);

        return $lista;
    }

    // --- quién entra ------------------------------------------------------

    public function test_un_envio_a_3_dias_entra_y_a_4_dias_no(): void
    {
        $tresDias = $this->programacion(null, '2026-11-08 09:00:00');
        $this->programacion(null, '2026-11-09 09:00:00');

        $r = $this->alertas();

        $this->assertSame(1, $r['total']);
        $this->assertSame($tresDias->id, $r['alertas'][0]['programacion_id']);
        $this->assertSame(3, $r['alertas'][0]['dias_restantes']);
        $this->assertSame('2026-10', $r['alertas'][0]['periodo']);
    }

    public function test_el_limite_es_por_dia_de_calendario_no_por_horas(): void
    {
        // Hoy 5/nov 10:00. El 8/nov 23:59 son más de 72 h, pero sigue siendo «a 3 días de calendario».
        $this->programacion(null, '2026-11-08 23:59:00');
        $this->programacion(null, '2026-11-09 00:01:00');

        $this->assertSame(1, $this->alertas()['total']);
    }

    public function test_una_programacion_inactiva_no_genera_alerta(): void
    {
        $this->programacion(null, '2026-11-06 09:00:00', ['activo' => false]);

        $this->assertSame(0, $this->alertas()['total']);
    }

    public function test_un_cliente_pausado_o_eliminado_no_genera_alerta(): void
    {
        $this->programacion(Cliente::factory()->create(['estado' => 'pausado']));
        $eliminado = Cliente::factory()->create(['estado' => 'activo']);
        $this->programacion($eliminado);
        $eliminado->delete();

        $this->assertSame(0, $this->alertas()['total']);
    }

    public function test_un_envio_vencido_que_sigue_pendiente_aparece_con_dias_negativos(): void
    {
        $this->programacion(null, '2026-11-03 09:00:00');

        $a = $this->alertas()['alertas'][0];

        $this->assertSame(-2, $a['dias_restantes']);
        $this->assertTrue($a['falta_reporte']);
    }

    public function test_sin_programacion_el_resultado_esta_vacio(): void
    {
        $this->assertSame(['hoy' => '2026-11-05', 'dias_aviso' => 3, 'total' => 0, 'con_pendientes' => 0, 'alertas' => []], $this->alertas());
    }

    // --- (a) reporte del periodo anterior --------------------------------

    public function test_avisa_si_falta_el_reporte_del_periodo_anterior(): void
    {
        $this->programacion();

        $a = $this->alertas()['alertas'][0];

        $this->assertTrue($a['falta_reporte']);
        $this->assertNull($a['reporte_id']);
        $this->assertTrue($a['requiere_atencion']);
    }

    public function test_un_reporte_listo_o_entregado_del_periodo_quita_el_aviso(): void
    {
        $listo = Cliente::factory()->create(['estado' => 'activo']);
        $entregado = Cliente::factory()->create(['estado' => 'activo']);
        $this->reporte($listo, 'listo');
        $this->reporte($entregado, 'entregado');
        $this->programacion($listo);
        $this->programacion($entregado);

        foreach ($this->alertas()['alertas'] as $a) {
            $this->assertFalse($a['falta_reporte']);
            $this->assertNotNull($a['reporte_id']);
        }
    }

    public function test_un_borrador_cuenta_como_faltante_pero_se_enlaza(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $borrador = $this->reporte($cliente, EstadoReporte::Borrador->value);
        $this->programacion($cliente);

        $a = $this->alertas()['alertas'][0];

        $this->assertTrue($a['falta_reporte']);
        $this->assertSame($borrador->id, $a['reporte_id']);
        $this->assertSame('borrador', $a['reporte_estado']);
    }

    public function test_el_reporte_de_otra_area_o_de_otro_mes_no_cuenta(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $this->reporte($cliente, 'listo', ['area' => AreaReporte::Ads->value]);
        $this->reporte($cliente, 'listo', ['periodo_inicio' => '2026-09-01', 'periodo_fin' => '2026-09-30']);
        $this->programacion($cliente);

        $this->assertTrue($this->alertas()['alertas'][0]['falta_reporte']);
    }

    // --- (b) celdas de captura del mes anterior --------------------------

    public function test_lista_las_celdas_del_mes_anterior_en_falta_o_parciales(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $this->listaDeKeywords($cliente); // sin ninguna medición en octubre
        $this->reporte($cliente);
        $this->programacion($cliente);

        $a = $this->alertas()['alertas'][0];

        $this->assertFalse($a['falta_reporte']);
        $this->assertTrue($a['requiere_atencion']);
        $this->assertSame(['keywords'], array_column($a['celdas_faltantes'], 'fuente'));
        $this->assertSame('falta', $a['celdas_faltantes'][0]['estado']);
        $this->assertSame('Posiciones de keywords', $a['celdas_faltantes'][0]['etiqueta']);
    }

    public function test_si_la_captura_del_mes_anterior_esta_completa_no_hay_celdas_faltantes(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $lista = $this->listaDeKeywords($cliente);
        KeywordMedicion::create(['keyword_id' => $lista->keywords()->first()->id, 'lista_id' => $lista->id, 'fecha' => '2026-10-15', 'posicion' => 4]);
        $this->reporte($cliente);
        $this->programacion($cliente);

        $a = $this->alertas()['alertas'][0];

        $this->assertSame([], $a['celdas_faltantes']);
        $this->assertFalse($a['requiere_atencion']);
        $this->assertSame(0, $this->alertas()['con_pendientes']);
    }

    public function test_las_celdas_son_del_mes_anterior_no_del_actual(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $lista = $this->listaDeKeywords($cliente);
        // Medida en noviembre (mes en curso), no en octubre: octubre sigue faltando.
        KeywordMedicion::create(['keyword_id' => $lista->keywords()->first()->id, 'lista_id' => $lista->id, 'fecha' => '2026-11-02', 'posicion' => 4]);
        $this->programacion($cliente);

        $this->assertSame(['keywords'], array_column($this->alertas()['alertas'][0]['celdas_faltantes'], 'fuente'));
    }

    public function test_el_cliente_activo_filtra_las_alertas(): void
    {
        $uno = Cliente::factory()->create(['estado' => 'activo']);
        $this->programacion($uno);
        $this->programacion();

        $this->assertSame(2, $this->alertas()['total']);
        $this->assertSame(1, $this->alertas($uno->id)['total']);
    }

    // --- panel y API ------------------------------------------------------

    public function test_el_tablero_muestra_el_bloque_reportes_por_salir(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo', 'empresa' => 'Hotel Fratelli']);
        $this->listaDeKeywords($cliente);
        $this->programacion($cliente);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index'))
            ->assertOk()
            ->assertSee('data-reportes-por-salir', false)
            ->assertSee('Reportes por salir')
            ->assertSee('Falta el reporte de 10/2026')
            ->assertSee('Posiciones de keywords: falta de 10/2026');
    }

    public function test_sin_envios_proximos_el_bloque_no_aparece(): void
    {
        $this->programacion(null, '2026-11-20 09:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.captura.index'))
            ->assertOk()
            ->assertDontSee('data-reportes-por-salir', false);
    }

    public function test_la_api_de_captura_agrega_alertas_programadas_sin_quitar_nada(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['captura:leer']);
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $this->programacion($cliente);

        $r = $this->getJson('/api/v1/captura')->assertOk();

        $r->assertJsonStructure(['data' => ['mes', 'resumen', 'filas', 'pendientes', 'alertas_programadas' => ['hoy', 'dias_aviso', 'total', 'con_pendientes', 'alertas']]]);
        $r->assertJsonPath('data.alertas_programadas.total', 1);
        $r->assertJsonPath('data.alertas_programadas.alertas.0.cliente_id', $cliente->id);
        $r->assertJsonPath('data.alertas_programadas.alertas.0.falta_reporte', true);
    }
}
