<?php

namespace Tests\Feature\Console;

use App\Enums\TipoCobro;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use App\Support\FinanzasMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** servicios:clasificar-cobro: reglas por (empresa + nombre), simulación por defecto, transacción e idempotencia. */
class ClasificarCobroServiciosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, Servicio> servicios de la base de ejemplo, por clave corta */
    private function baseDeEjemplo(): array
    {
        $fratelli = Cliente::factory()->create(['empresa' => 'Hotel Fratelli']);
        $finca = Cliente::factory()->create(['empresa' => 'Hotel Finca del Minero']);
        $crear = fn (Cliente $c, string $nombre, float $precio) => Servicio::factory()->create([
            'cliente_id' => $c->id, 'nombre' => $nombre, 'precio_mensual' => $precio, 'estado' => 'activo',
        ]);

        return [
            // Los nombres vienen como en la base real: sin acentos y con mayúsculas distintas.
            'ads' => $crear($fratelli, 'Google Ads', 1500),
            'hosting' => $crear($fratelli, 'Dominio + Hosting', 2265.22),
            'inversion' => $crear($fratelli, 'Inversion Ads', 2500),
            'web1' => $crear($fratelli, 'Pagina Web Hotel Fratelli', 7000),
            'seo1' => $crear($fratelli, '1 URL De SEO Gratuita', 0),
            'mes' => $crear($fratelli, 'Campaña Hotel Fratelli Mes Gratuito', 2000),
            'web2' => $crear($finca, 'Pagina Web Hotel La finca del minero', 7000),
            'seo2' => $crear($finca, '1 URL De SEO Gratuita', 0),
            // Misma regla de nombre pero en OTRA empresa: no debe tocarse (la clave es empresa + nombre).
            'ajeno' => $crear(Cliente::factory()->create(['empresa' => 'Otra Empresa']), 'Pagina Web Hotel Fratelli', 5000),
        ];
    }

    public function test_por_defecto_solo_simula_y_no_escribe(): void
    {
        $s = $this->baseDeEjemplo();

        $this->artisan('servicios:clasificar-cobro')
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();

        $this->assertSame(27265.22, round(FinanzasMetrics::mrr(), 2), 'sin --aplicar todo sigue mensual');
        $this->assertSame(TipoCobro::Mensual, $s['web1']->fresh()->tipo_cobro);
    }

    public function test_aplicar_reclasifica_por_empresa_y_nombre_y_deja_el_mrr_esperado(): void
    {
        $s = $this->baseDeEjemplo();

        $this->artisan('servicios:clasificar-cobro', ['--aplicar' => true])->assertSuccessful();

        foreach (['ads', 'hosting', 'inversion'] as $clave) {
            $this->assertSame(TipoCobro::Mensual, $s[$clave]->fresh()->tipo_cobro, $clave);
        }
        foreach (['web1', 'web2', 'mes'] as $clave) {
            $this->assertSame(TipoCobro::Unico, $s[$clave]->fresh()->tipo_cobro, $clave);
        }
        foreach (['seo1', 'seo2'] as $clave) {
            $this->assertSame(TipoCobro::Gratuito, $s[$clave]->fresh()->tipo_cobro, $clave);
        }
        $this->assertSame(TipoCobro::Mensual, $s['ajeno']->fresh()->tipo_cobro);

        // 1,500 + 2,265.22 + 2,500 de Fratelli + 5,000 del servicio ajeno que no se tocó.
        $this->assertSame(11265.22, round(FinanzasMetrics::mrr(), 2));
        // 7,000 + 2,000 + 7,000 de los tres de pago único.
        $this->assertSame(16000.0, FinanzasMetrics::ingresosPorProyecto()['total']);
        $this->assertDatabaseHas('servicio_eventos', ['servicio_id' => $s['web1']->id]);
    }

    public function test_es_idempotente_y_avisa_de_los_servicios_sin_regla(): void
    {
        $this->baseDeEjemplo();
        $this->artisan('servicios:clasificar-cobro', ['--aplicar' => true])->assertSuccessful();
        $eventos = \App\Models\ServicioEvento::count();

        $this->artisan('servicios:clasificar-cobro', ['--aplicar' => true])
            ->expectsOutputToContain('No hay servicios por reclasificar')
            ->expectsOutputToContain('Sin regla:')
            ->assertSuccessful();

        $this->assertSame($eventos, \App\Models\ServicioEvento::count(), 'la segunda corrida no escribe nada');
    }

    public function test_no_toca_finanzas(): void
    {
        $s = $this->baseDeEjemplo();
        Finanza::create([
            'cliente_id' => $s['web1']->cliente_id, 'servicio_id' => $s['web1']->id, 'concepto' => 'Anticipo', 'tipo' => 'ingreso',
            'monto' => 3500, 'estado' => 'pagado', 'fecha_emision' => now()->toDateString(), 'mes' => now()->month, 'anio' => now()->year,
        ]);

        $this->artisan('servicios:clasificar-cobro', ['--aplicar' => true])->assertSuccessful();

        $this->assertDatabaseCount('finanzas', 1);
        $this->assertDatabaseHas('finanzas', ['concepto' => 'Anticipo', 'monto' => 3500, 'estado' => 'pagado']);
    }
}
