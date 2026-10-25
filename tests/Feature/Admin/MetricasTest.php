<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo Métricas: definiciones por cliente, guardado mensual en bloque,
 * copia del mes anterior e histórico.
 */
class MetricasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El manifest de Vite versionado no conoce los assets nuevos hasta compilar.
        $this->withoutVite();
    }

    private function usuario(): User
    {
        return User::factory()->create();
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

    private function valor(MetricaCliente $metrica, string $periodo, float $valor): MetricaClienteValor
    {
        return MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => $periodo, 'valor' => $valor]);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('admin.metricas.index'))->assertRedirect(route('login'));
        $this->post(route('admin.metricas.valores.store'), [])->assertRedirect(route('login'));
        $this->post(route('admin.metricas.definiciones.store'), [])->assertRedirect(route('login'));
    }

    public function test_index_sin_cliente_y_con_cliente_responde_200(): void
    {
        $cliente = Cliente::factory()->create();
        $this->metrica($cliente);

        $this->actingAs($this->usuario())->get(route('admin.metricas.index'))->assertOk();
        $this->actingAs($this->usuario())
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->assertOk()
            ->assertSee('Llamadas')
            ->assertSee('Guardar mes');
    }

    public function test_crear_metrica(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->usuario())->post(route('admin.metricas.definiciones.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => 'Cotizaciones',
            'unidad' => '',
            'meta' => '12,5',
            'sentido' => 'mayor',
            'umbral_amarillo_pct' => 75,
        ])->assertRedirect(route('admin.metricas.index', ['cliente' => $cliente->id]));

        $metrica = MetricaCliente::firstOrFail();
        $this->assertSame('Cotizaciones', $metrica->nombre);
        $this->assertSame('12.50', $metrica->meta);
        $this->assertTrue($metrica->activa);
        $this->assertSame(75, $metrica->umbral_amarillo_pct);
    }

    public function test_crear_valida_nombre_y_sentido(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->usuario())->post(route('admin.metricas.definiciones.store'), [
            'cliente_id' => $cliente->id,
            'nombre' => '',
            'sentido' => 'otro',
            'umbral_amarillo_pct' => 80,
        ])->assertSessionHasErrors(['nombre', 'sentido']);

        $this->assertSame(0, MetricaCliente::count());
    }

    public function test_editar_metrica_no_cambia_cliente_ni_estado(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $metrica = $this->metrica($cliente, ['activa' => false]);

        $this->actingAs($this->usuario())->put(route('admin.metricas.definiciones.update', $metrica), [
            'cliente_id' => $otro->id,
            'nombre' => 'Llamadas nuevas',
            'meta' => '',
            'sentido' => 'menor',
            'umbral_amarillo_pct' => 90,
        ])->assertRedirect();

        $metrica->refresh();
        $this->assertSame('Llamadas nuevas', $metrica->nombre);
        $this->assertNull($metrica->meta);
        $this->assertSame('menor', $metrica->sentido->value);
        $this->assertSame($cliente->id, $metrica->cliente_id);
        $this->assertFalse($metrica->activa);
    }

    public function test_desactivar_conserva_el_historico_y_eliminar_lo_borra(): void
    {
        $cliente = Cliente::factory()->create();
        $metrica = $this->metrica($cliente);
        $this->valor($metrica, '2026-09-01', 40);
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('admin.metricas.definiciones.alternar', $metrica))->assertRedirect();
        $this->assertFalse($metrica->fresh()->activa);
        $this->assertSame(1, MetricaClienteValor::count());

        // Desactivada ya no aparece en la tabla de captura del mes.
        $this->actingAs($usuario)
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->assertDontSee('data-metrica-valor', false);

        $this->actingAs($usuario)->delete(route('admin.metricas.definiciones.destroy', $metrica))->assertRedirect();
        $this->assertSame(0, MetricaCliente::count());
        $this->assertSame(0, MetricaClienteValor::count());
    }

    public function test_guardar_mes_en_bloque_guarda_todas_las_filas(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente, ['nombre' => 'A']);
        $b = $this->metrica($cliente, ['nombre' => 'B']);
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id,
            'mes' => '2026-10',
            'valores' => [$a->id => '95', $b->id => '1,5'],
            'notas' => [$a->id => 'buen mes', $b->id => ''],
        ])->assertRedirect(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']));

        $this->assertSame(2, MetricaClienteValor::count());
        $va = MetricaClienteValor::where('metrica_id', $a->id)->first();
        $this->assertSame('95.00', $va->valor);
        $this->assertSame('2026-10-01', $va->periodo->toDateString());
        $this->assertSame('buen mes', $va->nota);
        $this->assertSame($usuario->id, $va->registrado_por);
        $vb = MetricaClienteValor::where('metrica_id', $b->id)->first();
        $this->assertSame('1.50', $vb->valor);
        $this->assertNull($vb->nota);
    }

    public function test_guardar_dos_veces_actualiza_sin_duplicar(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $payload = ['cliente_id' => $cliente->id, 'mes' => '2026-10'];
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('admin.metricas.valores.store'), $payload + ['valores' => [$a->id => '10']]);
        $this->actingAs($usuario)->post(route('admin.metricas.valores.store'), $payload + ['valores' => [$a->id => '20']]);

        $this->assertSame(1, MetricaClienteValor::count());
        $this->assertSame('20.00', MetricaClienteValor::first()->valor);
    }

    public function test_valor_vacio_no_guarda_cero_y_borra_el_existente(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente, ['nombre' => 'A']);
        $b = $this->metrica($cliente, ['nombre' => 'B']);
        $this->valor($b, '2026-10-01', 7);

        $this->actingAs($this->usuario())->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id,
            'mes' => '2026-10',
            'valores' => [$a->id => '', $b->id => '  '],
        ])->assertRedirect();

        $this->assertSame(0, MetricaClienteValor::count());
    }

    public function test_un_cero_escrito_si_se_guarda(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);

        $this->actingAs($this->usuario())->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id,
            'mes' => '2026-10',
            'valores' => [$a->id => '0'],
        ])->assertRedirect();

        $this->assertSame('0.00', MetricaClienteValor::first()->valor);
    }

    public function test_valor_no_numerico_o_mes_invalido_se_rechaza(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id, 'mes' => '2026-10', 'valores' => [$a->id => 'abc'],
        ])->assertSessionHasErrors('valores.'.$a->id);

        $this->actingAs($usuario)->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id, 'mes' => '2026-13', 'valores' => [$a->id => '1'],
        ])->assertSessionHasErrors('mes');

        $this->assertSame(0, MetricaClienteValor::count());
    }

    public function test_error_de_valor_conserva_lo_escrito_y_nombra_la_metrica(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente, ['nombre' => 'QA-Llamadas']);
        $b = $this->metrica($cliente, ['nombre' => 'QA-Otra']);

        $this->actingAs($this->usuario())
            ->from(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->post(route('admin.metricas.valores.store'), [
                'cliente_id' => $cliente->id, 'mes' => '2026-10', 'valores' => [$a->id => 'abc', $b->id => '45'],
            ])->assertRedirect();

        $this->actingAs($this->usuario())
            ->withSession(['_old_input' => ['valores' => [$a->id => 'abc', $b->id => '45']], 'errors' => new \Illuminate\Support\ViewErrorBag])
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->assertSee('value="45"', false);

        $this->actingAs($this->usuario())->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id, 'mes' => '2026-10', 'valores' => [$a->id => 'abc'],
        ])->assertSessionHasErrors('valores.'.$a->id);
        $this->assertStringContainsString('valor de QA-Llamadas', session('errors')->first('valores.'.$a->id));
    }

    public function test_parametros_en_forma_de_arreglo_no_dan_500(): void
    {
        $this->actingAs($this->usuario())->get('/admin/metricas?mes[]=x&cliente[]=1')->assertOk();
    }

    public function test_unique_metrica_periodo_a_nivel_de_bd(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $this->valor($a, '2026-10-01', 1);

        $this->expectException(QueryException::class);
        $this->valor($a, '2026-10-01', 2);
    }

    public function test_aislamiento_por_cliente_ignora_metricas_ajenas(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $propia = $this->metrica($cliente, ['nombre' => 'Propia']);
        $ajena = $this->metrica($otro, ['nombre' => 'Ajena']);

        $this->actingAs($this->usuario())->post(route('admin.metricas.valores.store'), [
            'cliente_id' => $cliente->id,
            'mes' => '2026-10',
            'valores' => [$propia->id => '5', $ajena->id => '99'],
        ])->assertRedirect();

        $this->assertSame(1, MetricaClienteValor::count());
        $this->assertSame($propia->id, MetricaClienteValor::first()->metrica_id);

        $this->actingAs($this->usuario())
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->assertSee('Propia')
            ->assertDontSee('Ajena');
    }

    public function test_copiar_mes_anterior_precarga_sin_guardar(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente, ['nombre' => 'A']);
        $b = $this->metrica($cliente, ['nombre' => 'B']);
        $this->valor($a, '2026-09-01', 42);
        $this->valor($b, '2026-09-01', 8);
        $this->valor($b, '2026-10-01', 9); // ya capturado: no se pisa

        $respuesta = $this->actingAs($this->usuario())
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10', 'copiar' => 1]));

        $respuesta->assertOk();
        $filas = collect($respuesta->viewData('filas'))->keyBy(fn ($f) => $f['metrica']->id);
        $this->assertSame('42.00', $filas[$a->id]['valor_input']);
        $this->assertTrue($filas[$a->id]['precargado']);
        $this->assertNull($filas[$a->id]['valor']);
        $this->assertSame('9.00', $filas[$b->id]['valor_input']);
        $this->assertFalse($filas[$b->id]['precargado']);
        $this->assertSame(3, MetricaClienteValor::count());
    }

    public function test_index_calcula_semaforo_y_variacion(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $this->valor($a, '2026-09-01', 50);
        $this->valor($a, '2026-10-01', 85);

        $filas = $this->actingAs($this->usuario())
            ->get(route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => '2026-10']))
            ->viewData('filas');

        $this->assertSame('amarillo', $filas[0]['semaforo']);
        $this->assertSame(35.0, $filas[0]['variacion']['abs']);
        $this->assertSame(70.0, $filas[0]['variacion']['pct']);
    }

    public function test_historico_muestra_12_meses_y_grafica(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->metrica($cliente);
        $this->valor($a, '2026-08-01', 60);
        $this->valor($a, '2026-10-01', 110);
        $this->valor($a, '2025-09-01', 5); // fuera de la ventana de 12 meses

        $respuesta = $this->actingAs($this->usuario())
            ->get(route('admin.metricas.show', ['metrica' => $a->id, 'mes' => '2026-10']));

        $respuesta->assertOk()->assertSee('data-metricas-grafica', false)->assertSee('data-grafica-meta', false);
        $meses = $respuesta->viewData('meses');
        $this->assertCount(12, $meses);
        $this->assertSame('2025-11', $meses[0]['mes']->format('Y-m'));
        $this->assertSame('2026-10', $meses[11]['mes']->format('Y-m'));
        $this->assertSame(['verde'], array_values(array_unique(array_column(array_filter($meses, fn ($m) => $m['valor'] !== null && (float) $m['valor'] >= 100), 'semaforo'))));
        $this->assertCount(2, $respuesta->viewData('grafica')['puntos']);
    }

    public function test_historico_de_metrica_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario())->get(route('admin.metricas.show', ['metrica' => 9999]))->assertNotFound();
    }

    public function test_editar_con_orden_vacio_no_da_500_y_conserva_el_orden(): void
    {
        $metrica = $this->metrica(Cliente::factory()->create(), ['orden' => 7]);

        $this->actingAs($this->usuario())->put(route('admin.metricas.definiciones.update', $metrica), [
            'nombre' => 'Llamadas', 'meta' => '100', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'orden' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7, $metrica->fresh()->orden);
    }

    public function test_meta_negativa_se_rechaza(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->usuario())->post(route('admin.metricas.definiciones.store'), [
            'cliente_id' => $cliente->id, 'nombre' => 'X', 'meta' => '-100', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80,
        ])->assertSessionHasErrors('meta');
        $this->assertSame(0, MetricaCliente::count());
    }

    public function test_guardar_valor_reintenta_si_otra_escritura_creo_el_mes_a_la_vez(): void
    {
        $metrica = $this->metrica(Cliente::factory()->create());
        $chocar = true;
        MetricaClienteValor::creating(function () use (&$chocar, $metrica) {
            if ($chocar) {
                $chocar = false;
                // Simula la escritura concurrente que gana la carrera por el único (métrica, periodo).
                \DB::table('metrica_cliente_valores')->insert(['metrica_id' => $metrica->id, 'periodo' => '2026-10-01', 'valor' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $registro = \App\Support\Metricas\GuardadorValores::guardar($metrica->id, \Carbon\Carbon::create(2026, 10, 1), 55, null, null);

        $this->assertSame(1, MetricaClienteValor::count());
        $this->assertSame(55.0, (float) $registro->valor);
        $this->assertSame(55.0, (float) MetricaClienteValor::first()->valor);
    }

    public function test_grafica_corta_la_linea_en_los_meses_sin_dato(): void
    {
        $a = $this->metrica(Cliente::factory()->create());
        // Ventana 2025-11..2026-10: may-jun tramo, jul sin dato, ago-sep-oct tramo.
        $this->valor($a, '2026-05-01', 10);
        $this->valor($a, '2026-06-01', 20);
        $this->valor($a, '2026-08-01', 30);
        $this->valor($a, '2026-09-01', 50);
        $this->valor($a, '2026-10-01', 40);

        $respuesta = $this->actingAs($this->usuario())
            ->get(route('admin.metricas.show', ['metrica' => $a->id, 'mes' => '2026-10']))->assertOk();

        $grafica = $respuesta->viewData('grafica');
        $this->assertCount(5, $grafica['puntos']);
        $this->assertCount(2, $grafica['lineas']);
        $this->assertCount(2, explode(' ', $grafica['lineas'][0]));
        $this->assertCount(3, explode(' ', $grafica['lineas'][1]));
        $respuesta->assertSee('data-grafica-linea', false);
    }

    public function test_grafica_con_puntos_aislados_no_dibuja_polilinea(): void
    {
        $a = $this->metrica(Cliente::factory()->create());
        $this->valor($a, '2026-08-01', 30);
        $this->valor($a, '2026-10-01', 40);

        $respuesta = $this->actingAs($this->usuario())
            ->get(route('admin.metricas.show', ['metrica' => $a->id, 'mes' => '2026-10']))->assertOk();

        $this->assertSame([], $respuesta->viewData('grafica')['lineas']);
        $this->assertCount(2, $respuesta->viewData('grafica')['puntos']);
        $respuesta->assertDontSee('data-grafica-linea', false);
    }
}
