<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El envío programado arma el PDF por el mismo Armador que el panel: una
 * sección interna (p. ej. saldo de la cuenta de anuncios) no debe salir por correo.
 */
class ReporteInternoProgramadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_envio_programado_no_incluye_las_secciones_internas(): void
    {
        Mail::fake();

        $cliente = Cliente::factory()->create();
        $reporte = Reporte::create([
            'cliente_id' => $cliente->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte de octubre',
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'estado' => EstadoReporte::Listo->value,
        ]);
        $texto = fn (string $cuerpo) => ['formato' => 'pares', 'bloques' => [['titulo' => 'Nota', 'cuerpo' => $cuerpo]]];
        $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'Para el cliente', 'orden' => 1, 'contenido' => $texto('visible'), 'visible' => true]);
        $reporte->secciones()->create(['tipo' => 'texto', 'titulo' => 'Pagos', 'orden' => 2, 'contenido' => $texto('SALDO-SECRETO'), 'visible' => true, 'interno' => true]);

        ReporteProgramacion::factory()->create([
            'cliente_id' => $cliente->id,
            'destinatarios' => ['cliente@example.com'],
            'proximo_envio_en' => now()->subMinutes(5),
            'dia_mes' => 5,
            'hora' => '09:00',
        ]);

        $vistas = [];
        view()->creator('pdf.reporte', function ($vista) use (&$vistas) {
            $vistas[] = $vista->getData();
        });

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        $this->assertNotEmpty($vistas, 'El comando debió armar el PDF.');
        foreach ($vistas as $datos) {
            $this->assertSame(['Para el cliente'], array_column($datos['secciones'], 'titulo'));
            $this->assertStringNotContainsString('SALDO-SECRETO', json_encode($datos['secciones']));
        }
        $this->assertSame(EstadoReporte::Entregado, $reporte->fresh()->estado);
    }
}
