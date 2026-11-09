<?php

namespace Tests\Feature\Admin;

use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\MetricaCliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;
use App\Support\Plantillas\AplicadorPlantilla;
use App\Support\Plantillas\Giros;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AplicadorPlantilla: crea servicios, métricas sin meta y keywords semilla; idempotente y transaccional. */
class AplicadorPlantillasTest extends TestCase
{
    use RefreshDatabase;

    private function aplicador(): AplicadorPlantilla
    {
        return app(AplicadorPlantilla::class);
    }

    /** Servicios de la plantilla con el precio capturado en el paso 3. */
    private function serviciosDe(string $giro, array $precios = []): array
    {
        return collect(Giros::buscar($giro)['servicios'])->map(fn ($s, $i) => $s + ['precio_mensual' => $precios[$i] ?? null])->all();
    }

    public function test_crea_servicios_metricas_y_keywords_del_hotel(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Aguascalientes']);

        $r = $this->aplicador()->aplicar($cliente, 'hotel', $this->serviciosDe('hotel', [8000, 12000, 25000, null]), 'Museo Regional');

        $this->assertSame(4, $r['servicios_creados']);
        $this->assertSame(5, $r['metricas_creadas']);
        $this->assertSame(3, $r['keywords_creadas']);
        $this->assertSame([], $r['keywords_omitidas']);

        $servicios = Servicio::where('cliente_id', $cliente->id)->get()->keyBy('nombre');
        $this->assertSame('mensual', $servicios['SEO local']->tipo_cobro->value);
        $this->assertSame('8000.00', (string) $servicios['SEO local']->precio_mensual);
        $this->assertSame('unico', $servicios['Página web']->tipo_cobro->value);
        $this->assertSame('gratuito', $servicios['1 URL de SEO']->tipo_cobro->value);
        $this->assertSame('0.00', (string) $servicios['1 URL de SEO']->precio_mensual);
        $this->assertSame('activo', $servicios['SEO local']->estado->value);
        // La bitácora del servicio queda como en el alta manual.
        $this->assertDatabaseHas('servicio_eventos', ['servicio_id' => $servicios['SEO local']->id]);

        $metricas = MetricaCliente::where('cliente_id', $cliente->id)->get();
        $this->assertEqualsCanonicalizing(['cotizaciones', 'clics_whatsapp', 'llamadas', 'cpa', 'posicion_media'], $metricas->pluck('metrica_estandar')->all());
        $this->assertTrue($metricas->every(fn ($m) => $m->meta === null), 'las métricas nacen sin meta');
        $this->assertSame('menor', $metricas->firstWhere('metrica_estandar', 'cpa')->sentido->value);

        $lista = KeywordLista::where('cliente_id', $cliente->id)->sole();
        $this->assertSame('Semilla — Hotel', $lista->nombre);
        $this->assertEqualsCanonicalizing(
            ['hotel en aguascalientes', 'hoteles en aguascalientes', 'hotel cerca de museo regional'],
            Keyword::where('lista_id', $lista->id)->pluck('keyword')->all()
        );
        $this->assertSame($lista->id, $r['lista_keywords_id']);
    }

    public function test_el_mrr_sale_solo_de_los_servicios_mensuales_con_la_fuente_unica(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'León']);

        $this->aplicador()->aplicar($cliente, 'hotel', $this->serviciosDe('hotel', [8000, 12000, 25000, 500]));

        $this->assertSame(20000.0, FinanzasMetrics::mrr());
        $this->assertSame(25000.0, FinanzasMetrics::ingresosPorProyecto($cliente->id)['total']);
    }

    public function test_omite_las_keywords_a_las_que_les_falta_el_dato(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Querétaro']);

        $r = $this->aplicador()->aplicar($cliente, 'hotel', []);

        $this->assertSame(2, $r['keywords_creadas']);
        $this->assertSame(['hotel cerca de {atraccion}'], $r['keywords_omitidas']);
        $this->assertDatabaseMissing('keywords', ['keyword' => 'hotel cerca de']);
    }

    public function test_sin_ciudad_ni_tema_no_crea_lista_de_keywords(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => null]);

        $r = $this->aplicador()->aplicar($cliente, 'servicios_profesionales', []);

        $this->assertSame(0, KeywordLista::count());
        $this->assertNull($r['lista_keywords_id']);
        $this->assertCount(2, $r['keywords_omitidas']);
        // Las métricas sí se crean: no dependen de datos del cliente.
        $this->assertSame(4, $r['metricas_creadas']);
    }

    public function test_es_idempotente_y_no_duplica(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Morelia']);
        $servicios = $this->serviciosDe('hotel', [1000, 2000, 3000, null]);

        $primera = $this->aplicador()->aplicar($cliente, 'hotel', $servicios, 'Catedral');
        $segunda = $this->aplicador()->aplicar($cliente, 'hotel', $servicios, 'Catedral');

        $this->assertSame(4, $primera['servicios_creados']);
        $this->assertSame(0, $segunda['servicios_creados']);
        $this->assertSame(4, $segunda['servicios_existentes']);
        $this->assertSame(0, $segunda['metricas_creadas']);
        $this->assertSame(5, $segunda['metricas_existentes']);
        $this->assertSame(0, $segunda['keywords_creadas']);
        $this->assertSame(3, $segunda['keywords_duplicadas']);

        $this->assertSame(4, Servicio::where('cliente_id', $cliente->id)->count());
        $this->assertSame(5, MetricaCliente::where('cliente_id', $cliente->id)->count());
        $this->assertSame(1, KeywordLista::where('cliente_id', $cliente->id)->count());
        $this->assertSame(3, Keyword::where('cliente_id', $cliente->id)->count());
    }

    public function test_no_toca_lo_que_el_cliente_ya_tiene(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Puebla']);
        $existente = Servicio::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'seo', 'nombre' => 'seo LOCAL', 'precio_mensual' => 7777]);
        $metrica = MetricaCliente::create([
            'cliente_id' => $cliente->id, 'nombre' => 'Llamadas', 'unidad' => 'llamadas', 'meta' => 40, 'sentido' => 'mayor',
            'fuente' => 'manual', 'metrica_estandar' => 'llamadas', 'umbral_amarillo_pct' => 80, 'activa' => true, 'orden' => 1,
        ]);

        $r = $this->aplicador()->aplicar($cliente, 'hotel', $this->serviciosDe('hotel', [100, 200, 300, null]));

        $this->assertSame(3, $r['servicios_creados']);
        $this->assertSame(1, $r['servicios_existentes']);
        $this->assertSame('7777.00', (string) $existente->fresh()->precio_mensual, 'el servicio existente conserva su precio');
        $this->assertSame(4, $r['metricas_creadas']);
        $this->assertSame('40.00', (string) $metrica->fresh()->meta, 'la métrica existente conserva su meta');
    }

    public function test_es_transaccional_si_falla_a_la_mitad_no_deja_nada(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Toluca']);
        $servicios = [
            ['tipo' => 'seo', 'nombre' => 'SEO local', 'tipo_cobro' => 'mensual', 'precio_mensual' => 100],
            ['tipo' => 'google_ads', 'nombre' => 'Google Ads', 'tipo_cobro' => 'mensual', 'precio_mensual' => 200],
            ['tipo' => 'tipo_que_no_existe', 'nombre' => 'Roto', 'tipo_cobro' => 'mensual', 'precio_mensual' => 300],
        ];

        try {
            $this->aplicador()->aplicar($cliente, 'hotel', $servicios);
            $this->fail('Debió lanzar una excepción por el tipo inválido.');
        } catch (\Throwable) {
            // esperado
        }

        $this->assertSame(0, Servicio::where('cliente_id', $cliente->id)->count());
        $this->assertSame(0, MetricaCliente::where('cliente_id', $cliente->id)->count());
        $this->assertSame(0, KeywordLista::count());
        $this->assertSame(0, Keyword::count());
        $this->assertDatabaseCount('servicio_eventos', 0);
    }

    public function test_sin_plantilla_solo_crea_los_servicios_dados(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Mérida']);

        $r = $this->aplicador()->aplicar($cliente, null, [['tipo' => 'software', 'nombre' => 'CRM a la medida', 'tipo_cobro' => 'unico', 'precio_mensual' => 9000]]);

        $this->assertSame(1, $r['servicios_creados']);
        $this->assertSame(0, $r['metricas_creadas']);
        $this->assertSame(0, KeywordLista::count());
    }

    public function test_no_crea_campanas_de_seo_ni_ads(): void
    {
        $cliente = Cliente::factory()->create(['ciudad' => 'Cancún']);

        $this->aplicador()->aplicar($cliente, 'hotel', $this->serviciosDe('hotel'));

        $this->assertSame(0, SeoCampana::count());
        $this->assertSame(0, AdsCampana::count());
    }

    public function test_cada_plantilla_se_aplica_sin_error_y_aisla_a_los_demas_clientes(): void
    {
        $otro = Cliente::factory()->create();

        foreach (Giros::claves() as $giro) {
            $cliente = Cliente::factory()->create(['ciudad' => 'Monterrey']);
            $r = $this->aplicador()->aplicar($cliente, $giro, $this->serviciosDe($giro), 'producto de prueba');
            $this->assertSame(count(Giros::buscar($giro)['servicios']), $r['servicios_creados'], $giro);
            $this->assertSame(count(Giros::buscar($giro)['metricas']), $r['metricas_creadas'], $giro);
        }

        $this->assertSame(0, Servicio::where('cliente_id', $otro->id)->count());
        $this->assertSame(0, MetricaCliente::where('cliente_id', $otro->id)->count());
    }
}
