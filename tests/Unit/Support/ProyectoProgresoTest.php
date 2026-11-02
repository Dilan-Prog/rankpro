<?php

namespace Tests\Unit\Support;

use App\Support\ProyectoProgreso;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ProyectoProgresoTest extends TestCase
{
    public function test_porcentaje_por_tareas_y_sin_tareas(): void
    {
        $this->assertSame(50, ProyectoProgreso::porTareas(4, 2));
        $this->assertSame(33, ProyectoProgreso::porTareas(3, 1));
        $this->assertSame(100, ProyectoProgreso::porTareas(2, 5), 'hechas > total no pasa de 100');
        $this->assertNull(ProyectoProgreso::porTareas(0, 0));
    }

    public function test_dias_restantes_positivo_cero_negativo_y_nulos(): void
    {
        $hoy = Carbon::parse('2026-11-01 15:30:00');

        $this->assertSame(5, ProyectoProgreso::diasRestantes(Carbon::parse('2026-11-06'), false, $hoy));
        $this->assertSame(0, ProyectoProgreso::diasRestantes(Carbon::parse('2026-11-01 08:00'), false, $hoy));
        $this->assertSame(-3, ProyectoProgreso::diasRestantes(Carbon::parse('2026-10-29'), false, $hoy));
        $this->assertNull(ProyectoProgreso::diasRestantes(null, false, $hoy));
        $this->assertNull(ProyectoProgreso::diasRestantes(Carbon::parse('2026-10-29'), true, $hoy), 'cerrado no se marca vencido');
    }

    public function test_chips_del_stack(): void
    {
        $this->assertSame(['Laravel', 'MySQL', 'Vue'], ProyectoProgreso::chipsStack('Laravel, MySQL / Vue,  Laravel'));
        $this->assertSame([], ProyectoProgreso::chipsStack(null));
        $this->assertSame([], ProyectoProgreso::chipsStack('  , '));
        $this->assertCount(2, ProyectoProgreso::chipsStack('a,b,c,d', 2));
    }

    public function test_linea_de_tiempo_posiciona_barras_y_separa_sin_fechas(): void
    {
        $hoy = Carbon::parse('2026-11-10');
        $lt = ProyectoProgreso::lineaTiempo([
            ['nombre' => 'A', 'fecha_inicio' => '2026-11-01', 'fecha_entrega_estimada' => '2026-11-30', 'porcentaje_avance' => 50, 'dias_restantes' => 20],
            ['nombre' => 'B', 'fecha_inicio' => '2026-12-01', 'fecha_entrega_estimada' => '2026-11-20', 'porcentaje_avance' => 0, 'dias_restantes' => -1],
            ['nombre' => 'C', 'fecha_inicio' => null, 'fecha_entrega_estimada' => '2026-12-01'],
        ], $hoy);

        $px = ProyectoProgreso::PX_DIA;
        $this->assertCount(2, $lt['barras']);
        $this->assertCount(1, $lt['sin_fechas']);
        $this->assertSame(0, $lt['barras'][0]['left']);
        $this->assertSame(30 * $px, $lt['barras'][0]['width']);
        $this->assertFalse($lt['barras'][0]['vencido']);
        // B tenía las fechas invertidas: se normaliza y queda marcado vencido.
        $this->assertSame(19 * $px, $lt['barras'][1]['left']);
        $this->assertTrue($lt['barras'][1]['vencido']);
        $this->assertCount(2, $lt['meses']); // nov y dic 2026
        $this->assertSame(61 * $px, $lt['ancho']);
        $this->assertSame(9 * $px + intdiv($px, 2), $lt['hoy_left']);
    }

    public function test_linea_de_tiempo_vacia_sin_fechas(): void
    {
        $lt = ProyectoProgreso::lineaTiempo([['nombre' => 'X', 'fecha_inicio' => null, 'fecha_entrega_estimada' => null]]);

        $this->assertSame([], $lt['barras']);
        $this->assertNull($lt['hoy_left']);
    }
}
