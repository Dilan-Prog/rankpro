<?php

namespace Tests\Unit\Support;

use App\Support\Calidad\Antiguedad;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Antigüedad de una cifra: fronteras exactas de cada umbral. "Antiguo" es MÁS de N días
 * (N exactos todavía es reciente). Sin base de datos; el reloj se congela para que 30 sea 30.
 */
class AntiguedadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function haceDias(int $dias): Carbon
    {
        return Carbon::now()->subDays($dias);
    }

    public function test_umbral_general_de_30_dias_en_sus_fronteras(): void
    {
        $this->assertSame(['estado' => 'reciente', 'dias' => 29, 'umbral' => 30], Antiguedad::evaluar($this->haceDias(29)));
        $this->assertSame(['estado' => 'reciente', 'dias' => 30, 'umbral' => 30], Antiguedad::evaluar($this->haceDias(30)));
        $this->assertSame(['estado' => 'antiguo', 'dias' => 31, 'umbral' => 30], Antiguedad::evaluar($this->haceDias(31)));
    }

    public function test_umbral_de_auditoria_de_90_dias_en_sus_fronteras(): void
    {
        $tipo = Antiguedad::TIPO_AUDITORIA;

        $this->assertSame('reciente', Antiguedad::evaluar($this->haceDias(89), null, $tipo)['estado']);
        $this->assertSame('reciente', Antiguedad::evaluar($this->haceDias(90), null, $tipo)['estado']);
        $this->assertSame('antiguo', Antiguedad::evaluar($this->haceDias(91), null, $tipo)['estado']);
        // Lo que es antiguo para una métrica (31 días) todavía es vigente para una auditoría trimestral.
        $this->assertSame('reciente', Antiguedad::evaluar($this->haceDias(31), null, $tipo)['estado']);
    }

    public function test_sin_fecha_es_sin_dato_y_nunca_antiguo(): void
    {
        $this->assertSame(['estado' => 'sin_dato', 'dias' => null, 'umbral' => 30], Antiguedad::evaluar(null));
        $this->assertSame('sin_dato', Antiguedad::evaluar(null, null, Antiguedad::TIPO_AUDITORIA)['estado']);
    }

    public function test_fecha_futura_cuenta_como_cero_dias_y_es_reciente(): void
    {
        $r = Antiguedad::evaluar(Carbon::now()->addDays(15));

        $this->assertSame('reciente', $r['estado']);
        $this->assertSame(0, $r['dias']);
    }

    public function test_tipo_desconocido_usa_el_umbral_general(): void
    {
        $this->assertSame('reciente', Antiguedad::evaluar($this->haceDias(30), null, 'inventado')['estado']);
        $this->assertSame('antiguo', Antiguedad::evaluar($this->haceDias(31), null, 'inventado')['estado']);
    }

    public function test_calculado_al_momento_no_tiene_umbral(): void
    {
        $this->assertSame(['estado' => 'reciente', 'dias' => 0, 'umbral' => null], Antiguedad::evaluar($this->haceDias(400), null, Antiguedad::TIPO_CALCULADO));
        $this->assertSame('reciente', Antiguedad::evaluar(null, null, Antiguedad::TIPO_CALCULADO)['estado']);
    }

    public function test_un_umbral_explicito_manda_sobre_el_del_tipo(): void
    {
        $this->assertSame('antiguo', Antiguedad::evaluar($this->haceDias(8), 7)['estado']);
        $this->assertSame('reciente', Antiguedad::evaluar($this->haceDias(7), 7)['estado']);
    }

    public function test_cuenta_por_dia_calendario_no_por_horas(): void
    {
        // Ayer a las 23:59 son 1 día aunque hayan pasado 12 horas; hace 30 días a las 00:00 siguen siendo 30.
        $this->assertSame(1, Antiguedad::evaluar(Carbon::parse('2026-11-09 23:59:00'))['dias']);
        $this->assertSame(30, Antiguedad::evaluar(Carbon::parse('2026-10-11 00:00:00'))['dias']);
    }

    public function test_fin_de_mes_y_conversion_de_fechas(): void
    {
        $this->assertSame('2026-02-28', Antiguedad::finDeMes(2, 2026)->format('Y-m-d'));
        $this->assertNull(Antiguedad::finDeMes(13, 2026));
        $this->assertNull(Antiguedad::finDeMes(null, 2026));
        $this->assertSame('2026-05-04', Antiguedad::aCarbon('2026-05-04')->format('Y-m-d'));
        $this->assertNull(Antiguedad::aCarbon('no es fecha'));
        $this->assertNull(Antiguedad::aCarbon(''));
    }
}
