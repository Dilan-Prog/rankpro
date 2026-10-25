<?php

namespace Tests\Unit\Support;

use App\Enums\SentidoMetrica;
use App\Support\Metricas\Semaforo;
use PHPUnit\Framework\TestCase;

/**
 * Semáforo de métricas: función pura, sin BD ni framework.
 */
class SemaforoTest extends TestCase
{
    public function test_sin_dato_si_no_hay_valor(): void
    {
        $this->assertSame('sin_dato', Semaforo::evaluar(null, 100, 'mayor', 80));
        $this->assertSame('sin_dato', Semaforo::evaluar(null, null, 'mayor', 80));
        $this->assertSame('sin_dato', Semaforo::evaluar('', 100, 'mayor', 80));
    }

    public function test_sin_meta_si_hay_valor_pero_no_meta(): void
    {
        $this->assertSame('sin_meta', Semaforo::evaluar(50, null, 'mayor', 80));
        $this->assertSame('sin_meta', Semaforo::evaluar(0, null, 'menor', 80));
    }

    public function test_valor_cero_es_dato_valido_no_sin_dato(): void
    {
        $this->assertSame('rojo', Semaforo::evaluar(0, 100, 'mayor', 80));
    }

    public function test_sentido_mayor(): void
    {
        $this->assertSame('verde', Semaforo::evaluar(100, 100, 'mayor', 80));
        $this->assertSame('verde', Semaforo::evaluar(150, 100, 'mayor', 80));
        $this->assertSame('amarillo', Semaforo::evaluar(80, 100, 'mayor', 80));
        $this->assertSame('amarillo', Semaforo::evaluar(99.99, 100, 'mayor', 80));
        $this->assertSame('rojo', Semaforo::evaluar(79.99, 100, 'mayor', 80));
    }

    public function test_umbral_personalizado(): void
    {
        $this->assertSame('rojo', Semaforo::evaluar(60, 100, 'mayor', 70));
        $this->assertSame('amarillo', Semaforo::evaluar(70, 100, 'mayor', 70));
        $this->assertSame('amarillo', Semaforo::evaluar(60, 100, 'mayor', 50));
    }

    public function test_sentido_menor_se_invierte(): void
    {
        $this->assertSame('verde', Semaforo::evaluar(100, 100, 'menor', 80));
        $this->assertSame('verde', Semaforo::evaluar(50, 100, 'menor', 80));
        $this->assertSame('amarillo', Semaforo::evaluar(120, 100, 'menor', 80));
        $this->assertSame('rojo', Semaforo::evaluar(120.01, 100, 'menor', 80));
    }

    public function test_acepta_el_enum_y_valores_decimales_como_string(): void
    {
        $this->assertSame('verde', Semaforo::evaluar('120.00', '100.00', SentidoMetrica::Mayor, 80));
        $this->assertSame('rojo', Semaforo::evaluar('150.00', '100.00', SentidoMetrica::Menor, 80));
    }

    public function test_meta_cero_no_divide_entre_cero_sentido_mayor(): void
    {
        $this->assertSame('verde', Semaforo::evaluar(0, 0, 'mayor', 80));
        $this->assertSame('verde', Semaforo::evaluar(5, 0, 'mayor', 80));
        $this->assertSame('rojo', Semaforo::evaluar(-1, 0, 'mayor', 80));
    }

    public function test_meta_cero_sentido_menor(): void
    {
        $this->assertSame('verde', Semaforo::evaluar(0, 0, 'menor', 80));
        $this->assertSame('rojo', Semaforo::evaluar(1, 0, 'menor', 80));
    }
}
