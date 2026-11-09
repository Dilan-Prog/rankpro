<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Support\Conversiones\Deduplicador;
use App\Support\Conversiones\RegistradorConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Deduplicación de conversiones (doble clic en WhatsApp): servicio, comando artisan y registro
 * de las nuevas. La regla: mismo cliente + tipo + visitor_id (o gclid sin visitor_id) dentro de
 * una ventana contada desde la primera; la primera se conserva y las demás se MARCAN, no se borran.
 */
class ConversionesDeduplicacionTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = Carbon::parse('2026-11-08 10:00:00');
        Carbon::setTestNow($this->base->copy()->addDay());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** created_at no es $fillable: se fija con forceFill después de crear. */
    private function conv(Cliente $cliente, int $segundos, array $extra = []): AdsConversion
    {
        $c = AdsConversion::create($extra + [
            'cliente_id' => $cliente->id,
            'visitor_id' => 'visitante-1',
            'tipo' => 'whatsapp',
        ]);
        $c->forceFill(['created_at' => $this->base->copy()->addSeconds($segundos)])->save();

        return $c;
    }

    // --- Servicio -------------------------------------------------------------------------

    public function test_dentro_de_la_ventana_conserva_la_primera_y_marca_las_demas(): void
    {
        $cliente = Cliente::factory()->create();
        $primera = $this->conv($cliente, 0);
        $segunda = $this->conv($cliente, 20);
        $tercera = $this->conv($cliente, 200);

        $detectadas = (new Deduplicador(5))->detectar();

        $this->assertSame([$segunda->id, $tercera->id], $detectadas->pluck('id')->all());
        $this->assertSame([$primera->id, $primera->id], $detectadas->pluck('original_id')->all());

        $this->assertSame(2, (new Deduplicador(5))->aplicar($detectadas));
        $this->assertNull($primera->fresh()->duplicada_de_id);
        $this->assertSame($primera->id, $segunda->fresh()->duplicada_de_id);
        $this->assertNotNull($segunda->fresh()->duplicada_at);
        $this->assertSame(3, AdsConversion::count(), 'nunca se borra nada');
    }

    public function test_la_ventana_se_cuenta_desde_la_primera_con_1_5_y_10_minutos(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0);
        $a90 = $this->conv($cliente, 90);
        $a420 = $this->conv($cliente, 420);
        $a900 = $this->conv($cliente, 900);

        $ids = fn (int $min) => (new Deduplicador($min))->detectar()->pluck('id')->all();

        $this->assertSame([], $ids(1), 'a 1 min ninguna de las otras cae dentro');
        $this->assertSame([$a90->id], $ids(5));
        // A 10 min cae la de 420 s; la de 900 s ya queda fuera de la ventana de la original.
        $this->assertSame([$a90->id, $a420->id], $ids(10));
        $this->assertNotContains($a900->id, $ids(10));
    }

    public function test_el_limite_exacto_de_la_ventana_cuenta_como_duplicada(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0);
        $justo = $this->conv($cliente, 300);
        $this->conv($cliente, 301, ['visitor_id' => 'otro']);

        $this->assertSame([$justo->id], (new Deduplicador(5))->detectar()->pluck('id')->all());
    }

    public function test_la_ventana_no_se_encadena_una_conversion_fuera_de_ella_vuelve_a_ser_original(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0);
        $this->conv($cliente, 240);          // dentro de 5 min de la primera
        $tercera = $this->conv($cliente, 480); // 8 min desde la primera: legítima aunque esté a 4 de la segunda
        $cuarta = $this->conv($cliente, 540);  // dentro de 5 min de la tercera

        $detectadas = (new Deduplicador(5))->detectar();
        $this->assertNotContains($tercera->id, $detectadas->pluck('id')->all());
        $this->assertSame($tercera->id, $detectadas->firstWhere('id', $cuarta->id)['original_id']);
    }

    public function test_agrupa_por_gclid_cuando_no_hay_visitor_id(): void
    {
        $cliente = Cliente::factory()->create();
        $primera = $this->conv($cliente, 0, ['visitor_id' => '', 'gclid' => 'G1']);
        $segunda = $this->conv($cliente, 30, ['visitor_id' => '', 'gclid' => 'G1']);
        $this->conv($cliente, 40, ['visitor_id' => '', 'gclid' => 'G2']);

        $detectadas = (new Deduplicador(5))->detectar();

        $this->assertSame([$segunda->id], $detectadas->pluck('id')->all());
        $this->assertSame($primera->id, $detectadas->first()['original_id']);
    }

    public function test_con_visitor_id_el_gclid_distinto_no_las_separa(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0, ['gclid' => 'A']);
        $segunda = $this->conv($cliente, 30, ['gclid' => 'B']);

        $this->assertSame([$segunda->id], (new Deduplicador(5))->detectar()->pluck('id')->all());
    }

    public function test_sin_visitor_ni_gclid_nunca_se_marca(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0, ['visitor_id' => '', 'gclid' => null]);
        $this->conv($cliente, 5, ['visitor_id' => '', 'gclid' => null]);
        $this->conv($cliente, 10, ['visitor_id' => '', 'gclid' => '']);

        $this->assertCount(0, (new Deduplicador(5))->detectar());
    }

    public function test_no_cruza_clientes_ni_tipos(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->conv($a, 0);
        $this->conv($b, 10);                              // mismo visitante, otro cliente
        $this->conv($a, 20, ['tipo' => 'llamada']);       // mismo visitante, otro tipo

        $this->assertCount(0, (new Deduplicador(5))->detectar());
    }

    public function test_es_idempotente(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0);
        $segunda = $this->conv($cliente, 10);
        $dedup = new Deduplicador(5);

        $this->assertSame(1, $dedup->aplicar());
        $marca = $segunda->fresh()->duplicada_at;

        $this->assertCount(0, $dedup->detectar());
        $this->assertSame(0, $dedup->aplicar());
        $this->assertEquals($marca, $segunda->fresh()->duplicada_at);
    }

    public function test_una_original_marcada_a_mano_no_sirve_de_ancla(): void
    {
        $cliente = Cliente::factory()->create();
        $primera = $this->conv($cliente, 0);
        $marcada = $this->conv($cliente, 10, ['duplicada_de_id' => $primera->id, 'duplicada_at' => now()]);
        $tercera = $this->conv($cliente, 20);

        $detectadas = (new Deduplicador(5))->detectar();

        $this->assertSame([$tercera->id], $detectadas->pluck('id')->all());
        $this->assertSame($primera->id, $detectadas->first()['original_id']);
        $this->assertNotContains($marcada->id, $detectadas->pluck('id')->all());
    }

    public function test_ventana_invalida_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Deduplicador(0);
    }

    // --- Comando --------------------------------------------------------------------------

    public function test_el_comando_por_defecto_solo_simula_y_muestra_la_tabla(): void
    {
        $cliente = Cliente::factory()->create();
        $primera = $this->conv($cliente, 0);
        $segunda = $this->conv($cliente, 20);

        $this->artisan('conversiones:deduplicar')
            ->expectsOutputToContain('SIMULACIÓN')
            ->expectsOutputToContain('Se marcarían ahora: 1')
            ->assertSuccessful();

        $this->assertNull($segunda->fresh()->duplicada_de_id, 'la simulación no escribe');
        $this->assertNull($primera->fresh()->duplicada_de_id);
    }

    public function test_el_comando_con_aplicar_escribe_y_deja_el_recuento(): void
    {
        $cliente = Cliente::factory()->create();
        $primera = $this->conv($cliente, 0);
        $segunda = $this->conv($cliente, 20);

        $this->artisan('conversiones:deduplicar', ['--aplicar' => true])
            ->expectsOutputToContain('Marcadas ahora: 1. Total de duplicadas: 1 de 2')
            ->assertSuccessful();

        $this->assertSame($primera->id, $segunda->fresh()->duplicada_de_id);

        // Segunda corrida: nada nuevo.
        $this->artisan('conversiones:deduplicar', ['--aplicar' => true])
            ->expectsOutputToContain('Marcadas ahora: 0')
            ->assertSuccessful();
    }

    public function test_el_comando_respeta_la_ventana(): void
    {
        $cliente = Cliente::factory()->create();
        $this->conv($cliente, 0);
        $b = $this->conv($cliente, 400); // 6 min 40 s

        $this->artisan('conversiones:deduplicar', ['--aplicar' => true, '--ventana' => 5])->assertSuccessful();
        $this->assertNull($b->fresh()->duplicada_de_id);

        $this->artisan('conversiones:deduplicar', ['--aplicar' => true, '--ventana' => 10])->assertSuccessful();
        $this->assertNotNull($b->fresh()->duplicada_de_id);
    }

    public function test_el_comando_rechaza_una_ventana_invalida_y_limita_por_cliente(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $this->conv($a, 0);
        $da = $this->conv($a, 10);
        $this->conv($b, 0);
        $db = $this->conv($b, 10);

        $this->artisan('conversiones:deduplicar', ['--ventana' => 'abc'])->assertFailed();
        $this->artisan('conversiones:deduplicar', ['--ventana' => 0])->assertFailed();

        $this->artisan('conversiones:deduplicar', ['--aplicar' => true, '--cliente' => $a->id])->assertSuccessful();
        $this->assertNotNull($da->fresh()->duplicada_de_id);
        $this->assertNull($db->fresh()->duplicada_de_id);
    }

    // --- Registro de nuevas -----------------------------------------------------------------

    public function test_registrar_una_conversion_dentro_de_la_ventana_la_guarda_marcada(): void
    {
        $cliente = Cliente::factory()->create();
        Carbon::setTestNow($this->base);

        $primera = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'whatsapp']);
        Carbon::setTestNow($this->base->copy()->addSeconds(30));
        $segunda = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'whatsapp', 'valor' => 50]);

        $this->assertNull($primera->fresh()->duplicada_de_id);
        $this->assertSame($primera->id, $segunda->fresh()->duplicada_de_id);
        $this->assertSame(2, AdsConversion::count(), 'el dato no se pierde');
        $this->assertEquals(50, $segunda->fresh()->valor);
    }

    public function test_registrar_pasada_la_ventana_o_de_otro_tipo_o_visitante_no_marca(): void
    {
        $cliente = Cliente::factory()->create();
        Carbon::setTestNow($this->base);
        RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'whatsapp']);

        Carbon::setTestNow($this->base->copy()->addSeconds(10));
        $otroTipo = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'llamada']);
        $otroVisitante = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v2', 'tipo' => 'whatsapp']);

        Carbon::setTestNow($this->base->copy()->addMinutes(6));
        $tarde = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'whatsapp']);

        $this->assertNull($otroTipo->fresh()->duplicada_de_id);
        $this->assertNull($otroVisitante->fresh()->duplicada_de_id);
        $this->assertNull($tarde->fresh()->duplicada_de_id);
    }

    public function test_registrar_con_gclid_y_sin_visitor_id_usa_el_gclid_como_clave(): void
    {
        $cliente = Cliente::factory()->create();
        Carbon::setTestNow($this->base);
        $primera = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => '', 'tipo' => 'whatsapp', 'gclid' => 'GX']);
        Carbon::setTestNow($this->base->copy()->addSeconds(5));
        $segunda = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => '', 'tipo' => 'whatsapp', 'gclid' => 'GX']);
        $sinNada = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => '', 'tipo' => 'whatsapp']);

        $this->assertSame($primera->id, $segunda->fresh()->duplicada_de_id);
        $this->assertNull($sinNada->fresh()->duplicada_de_id);
    }

    public function test_registrar_copia_la_campana_del_clic(): void
    {
        $cliente = Cliente::factory()->create();
        $campana = AdsCampana::factory()->create(['cliente_id' => $cliente->id]);
        AdsClic::create(['cliente_id' => $cliente->id, 'visitor_id' => 'v1', 'ads_campana_id' => $campana->id, 'gclid' => 'GC', 'landing_url' => 'https://ejemplo.com/']);

        $conversion = RegistradorConversion::registrarConversion($cliente, ['visitor_id' => 'v1', 'tipo' => 'whatsapp']);

        $this->assertSame($campana->id, $conversion->fresh()->ads_campana_id);
        $this->assertSame($campana->nombre, $conversion->fresh()->campanaNombre());
    }
}
