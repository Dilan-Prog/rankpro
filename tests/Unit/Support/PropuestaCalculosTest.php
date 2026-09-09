<?php

namespace Tests\Unit\Support;

use App\Support\Propuestas\Calculos;
use Tests\TestCase;

/**
 * Calculos is a pure static class (no Eloquent/DB involved), so this extends
 * the base TestCase without RefreshDatabase — nothing here touches the
 * database.
 */
class PropuestaCalculosTest extends TestCase
{
    // --- variacion -----------------------------------------------------------

    public function test_variacion_increase(): void
    {
        $resultado = Calculos::variacion('100', '150');

        $this->assertStringContainsString('+50', $resultado['delta']);
        $this->assertTrue($resultado['sube']);
    }

    public function test_variacion_decrease(): void
    {
        $resultado = Calculos::variacion('100', '80');

        $this->assertStringContainsString('-20', $resultado['delta']);
        $this->assertFalse($resultado['sube']);
    }

    public function test_variacion_guards_against_division_by_zero(): void
    {
        $resultado = Calculos::variacion('0', '50');

        $this->assertNull($resultado['delta']);
        $this->assertNull($resultado['sube']);
    }

    public function test_variacion_with_null_input_returns_null(): void
    {
        $resultado = Calculos::variacion(null, '50');

        $this->assertNull($resultado['delta']);
        $this->assertNull($resultado['sube']);
    }

    public function test_variacion_with_empty_string_input_returns_null(): void
    {
        $resultado = Calculos::variacion('', '50');

        $this->assertNull($resultado['delta']);
        $this->assertNull($resultado['sube']);
    }

    /**
     * Los valores reales vienen de campos de texto libre, así que pueden
     * traer separador de miles ("1,068") o símbolo de porcentaje ("3.09%").
     * Calculos::numero() los limpia con preg_replace('/[^0-9.\-]/', '', ...),
     * así que la coma se descarta y "1,068" se lee como 1068.
     */
    public function test_variacion_strips_thousands_separator_commas(): void
    {
        $resultado = Calculos::variacion('1,068', '1,200');

        // (1200 - 1068) / 1068 * 100 = 12.359...% -> redondeado a 2 decimales.
        $this->assertSame('+12.36%', $resultado['delta']);
        $this->assertTrue($resultado['sube']);
    }

    public function test_variacion_strips_percent_sign(): void
    {
        $resultado = Calculos::variacion('2%', '4%');

        $this->assertSame('+100.00%', $resultado['delta']);
        $this->assertTrue($resultado['sube']);
    }

    // --- numero ----------------------------------------------------------------

    public function test_numero_strips_non_numeric_noise(): void
    {
        $this->assertSame(1068.0, Calculos::numero('1,068'));
        $this->assertSame(3.09, Calculos::numero('3.09%'));
    }

    public function test_numero_returns_null_for_blank_or_null_input(): void
    {
        $this->assertNull(Calculos::numero(null));
        $this->assertNull(Calculos::numero(''));
        $this->assertNull(Calculos::numero('   '));
    }

    // --- subtotal ----------------------------------------------------------------

    public function test_subtotal_multiplies_horas_by_tarifa(): void
    {
        $this->assertSame(1500.0, Calculos::subtotal(10, '150.00'));
    }

    public function test_subtotal_is_null_when_horas_is_null(): void
    {
        $this->assertNull(Calculos::subtotal(null, '150.00'));
    }

    public function test_subtotal_is_null_when_tarifa_is_null(): void
    {
        $this->assertNull(Calculos::subtotal(10, null));
    }
}
