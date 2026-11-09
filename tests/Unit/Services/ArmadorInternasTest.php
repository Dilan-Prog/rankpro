<?php

namespace Tests\Unit\Services;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Services\Reportes\Armador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Armador: las secciones internas se excluyen de todo entregable (PDF, XLSX,
 * envío programado y API pasan por armar()), pero siguen existiendo en el editor.
 */
class ArmadorInternasTest extends TestCase
{
    use RefreshDatabase;

    private function reporte(): Reporte
    {
        return Reporte::create([
            'cliente_id' => Cliente::factory()->create()->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte mensual',
            'periodo_inicio' => '2026-08-01',
            'periodo_fin' => '2026-08-31',
            'estado' => EstadoReporte::Borrador->value,
        ]);
    }

    private function texto(Reporte $reporte, string $titulo, int $orden, array $extra = []): void
    {
        $reporte->secciones()->create($extra + [
            'tipo' => 'texto',
            'titulo' => $titulo,
            'orden' => $orden,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Dato', 'cuerpo' => $titulo]]],
            'visible' => true,
        ]);
    }

    public function test_armar_excluye_las_secciones_internas(): void
    {
        $reporte = $this->reporte();
        $this->texto($reporte, 'Resumen', 1);
        $this->texto($reporte, 'Saldo de la cuenta', 2, ['interno' => true]);
        $this->texto($reporte, 'Cierre', 3);

        $armado = (new Armador())->armar($reporte);

        $this->assertSame(['Resumen', 'Cierre'], array_column($armado['secciones'], 'titulo'));
    }

    public function test_armar_con_solo_secciones_internas_devuelve_secciones_vacias(): void
    {
        $reporte = $this->reporte();
        $this->texto($reporte, 'Saldo', 1, ['interno' => true]);

        $this->assertSame([], (new Armador())->armar($reporte)['secciones']);
    }

    public function test_una_seccion_nueva_no_es_interna_por_defecto(): void
    {
        $reporte = $this->reporte();
        $this->texto($reporte, 'Resumen', 1);

        $this->assertFalse($reporte->secciones()->first()->fresh()->interno);
        $this->assertFalse($reporte->secciones()->first()->toRow()['interno']);
    }

    public function test_una_seccion_interna_sigue_existiendo_para_el_editor(): void
    {
        $reporte = $this->reporte();
        $this->texto($reporte, 'Saldo', 1, ['interno' => true]);

        $this->assertSame(1, $reporte->secciones()->count());
        $this->assertTrue($reporte->secciones()->first()->toRow()['interno']);
    }
}
