<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\User;
use App\Support\Captura\TableroCaptura;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Perfil completo del cliente y su fuente `perfil` en el tablero de pendientes de captura (no mensual). */
class CapturaPerfilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tablero(string $mes = '2026-03'): array
    {
        return app(TableroCaptura::class)->calcular(Carbon::createFromFormat('!Y-m', $mes));
    }

    private function celdaPerfil(array $tablero, Cliente $cliente): array
    {
        foreach ($tablero['filas'] as $fila) {
            if ($fila['cliente']->id === $cliente->id) {
                return $fila['celdas']['perfil'];
            }
        }
        $this->fail('El cliente no está en el tablero.');
    }

    // ---- modelo ---------------------------------------------------------------

    public function test_perfil_completo_exige_contacto_correo_whatsapp_y_renovacion(): void
    {
        $this->assertTrue(Cliente::factory()->create()->perfilCompleto());

        foreach ([
            'contacto_nombre' => 'Persona de contacto',
            'email' => 'Correo',
            'telefono' => 'WhatsApp',
            'fecha_renovacion_contrato' => 'Fecha de renovación',
        ] as $campo => $etiqueta) {
            // El nombre se iguala al comercial para que, sin contacto_nombre, tampoco haya contacto por `nombre`.
            $cliente = Cliente::factory()->create(['empresa' => 'Negocio', 'nombre' => 'Negocio', $campo => null]);
            $this->assertFalse($cliente->perfilCompleto(), $campo);
            $this->assertSame([$campo => $etiqueta], $cliente->camposFaltantes(), $campo);
        }
    }

    public function test_el_contacto_tambien_cuenta_si_nombre_guarda_a_la_persona(): void
    {
        // Fichas viejas: contacto_nombre vacío pero `nombre` es la persona y `empresa` el negocio.
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Ruiz', 'empresa' => 'Hotel Uno', 'contacto_nombre' => null]);

        $this->assertTrue($cliente->perfilCompleto());
    }

    public function test_campos_en_blanco_cuentan_como_vacios(): void
    {
        $cliente = Cliente::factory()->create(['email' => '   ', 'telefono' => '']);

        $this->assertEqualsCanonicalizing(['email', 'telefono'], array_keys($cliente->camposFaltantes()));
    }

    // ---- tablero ---------------------------------------------------------------

    public function test_la_fuente_perfil_existe_y_se_etiqueta(): void
    {
        $this->assertSame('Perfil del cliente', TableroCaptura::FUENTES['perfil']);
    }

    public function test_una_unidad_por_cliente_activo_hecha_si_el_perfil_esta_completo(): void
    {
        $completo = Cliente::factory()->create();
        $incompleto = Cliente::factory()->create(['empresa' => 'Inc', 'nombre' => 'Inc', 'telefono' => null]);
        Cliente::factory()->create(['estado' => 'pausado', 'telefono' => null]);

        $t = $this->tablero();

        $this->assertCount(2, $t['filas'], 'solo clientes activos');
        $a = $this->celdaPerfil($t, $completo);
        $this->assertSame(['estado' => TableroCaptura::COMPLETO, 'hechas' => 1, 'total' => 1], array_intersect_key($a, array_flip(['estado', 'hechas', 'total'])));
        $b = $this->celdaPerfil($t, $incompleto);
        $this->assertSame(TableroCaptura::FALTA, $b['estado']);
        $this->assertSame(0, $b['hechas']);
    }

    public function test_el_enlace_lleva_a_editar_el_cliente(): void
    {
        $cliente = Cliente::factory()->create(['email' => null]);

        $url = $this->celdaPerfil($this->tablero(), $cliente)['url'];

        $this->assertSame(route('admin.clientes.index', ['editar' => $cliente->id]), $url);
    }

    public function test_ignora_el_mes(): void
    {
        $cliente = Cliente::factory()->create(['telefono' => null]);

        foreach (['2020-01', '2026-03', '2031-12'] as $mes) {
            $this->assertSame(TableroCaptura::FALTA, $this->celdaPerfil($this->tablero($mes), $cliente)['estado'], $mes);
        }
    }

    public function test_un_perfil_incompleto_cuenta_como_pendiente_pero_no_da_fuentes_de_medicion(): void
    {
        $sinFuentes = Cliente::factory()->create(['telefono' => null]);
        $conMetrica = Cliente::factory()->create();
        MetricaCliente::create(['cliente_id' => $conMetrica->id, 'nombre' => 'M', 'unidad' => 'x', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'activa' => true, 'orden' => 1]);

        $t = $this->tablero();
        $filaSin = collect($t['filas'])->first(fn ($f) => $f['cliente']->id === $sinFuentes->id);

        $this->assertTrue($filaSin['sin_fuentes'], 'el perfil no es una fuente de medición');
        $this->assertSame(1, $filaSin['pendientes']);
        $this->assertSame(1, $t['resumen']['clientes_sin_fuentes']);
        // Pendientes: perfil del primero + métrica del segundo; total: 2 perfiles + 1 métrica.
        $this->assertSame(2, $t['resumen']['celdas_pendientes']);
        $this->assertSame(3, $t['resumen']['celdas_total']);
        $this->assertSame(2, $t['resumen']['clientes_con_pendientes']);
    }

    public function test_solo_pendientes_muestra_a_quien_le_falta_el_perfil(): void
    {
        $completo = Cliente::factory()->create();
        MetricaCliente::create(['cliente_id' => $completo->id, 'nombre' => 'M', 'unidad' => 'x', 'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'activa' => true, 'orden' => 1]);
        $incompleto = Cliente::factory()->create(['email' => null]);

        $t = app(TableroCaptura::class)->calcular(Carbon::createFromFormat('!Y-m', '2026-03'), true);

        $this->assertSame([$incompleto->id, $completo->id], array_map(fn ($f) => $f['cliente']->id, collect($t['filas'])->sortBy(fn ($f) => $f['cliente']->id === $incompleto->id ? 0 : 1)->values()->all()));
    }

    public function test_la_pantalla_muestra_la_columna_y_la_celda_del_perfil(): void
    {
        $cliente = Cliente::factory()->create(['telefono' => null]);

        $this->actingAs(User::factory()->create())->get(route('admin.captura.index', ['mes' => '2026-03']))
            ->assertOk()
            ->assertSee('Perfil del cliente')
            ->assertSee('data-fuente="perfil"', false)
            ->assertSee(e(route('admin.clientes.index', ['editar' => $cliente->id])), false);
    }

    public function test_la_api_incluye_la_fuente_perfil_como_dato_nuevo(): void
    {
        Cliente::factory()->create(['telefono' => null]);
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);

        $r = $this->getJson('/api/v1/captura?mes=2026-03')->assertOk();

        $this->assertContains('perfil', array_column($r->json('data.filas.0.celdas'), 'fuente'));
        $this->assertContains('perfil', array_column($r->json('data.pendientes'), 'fuente'));
    }
}
