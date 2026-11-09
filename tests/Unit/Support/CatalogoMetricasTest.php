<?php

namespace Tests\Unit\Support;

use App\Enums\SentidoMetrica;
use App\Support\Metricas\Catalogo;
use App\Support\Metricas\Semaforo;
use PHPUnit\Framework\TestCase;

/**
 * Catálogo estándar de métricas: estructura estática, sin BD.
 */
class CatalogoMetricasTest extends TestCase
{
    public function test_las_claves_son_unicas_y_coinciden_con_la_clave_de_cada_entrada(): void
    {
        $claves = Catalogo::claves();

        $this->assertNotEmpty($claves);
        $this->assertSame(count($claves), count(array_unique($claves)));
        foreach (Catalogo::todos() as $clave => $entrada) {
            $this->assertSame($clave, $entrada['clave']);
            $this->assertLessThanOrEqual(40, strlen($clave), 'metrica_estandar es string(40)');
        }
    }

    public function test_cada_entrada_tiene_unidad_sentido_y_fuente_validos(): void
    {
        foreach (Catalogo::todos() as $clave => $entrada) {
            $this->assertNotSame('', trim($entrada['nombre']), $clave);
            $this->assertLessThanOrEqual(120, mb_strlen($entrada['nombre']), $clave);
            $this->assertNotSame('', trim($entrada['unidad']), $clave);
            $this->assertLessThanOrEqual(20, mb_strlen($entrada['unidad']), "{$clave}: unidad es string(20)");
            $this->assertNotNull(SentidoMetrica::tryFrom($entrada['sentido']), "{$clave}: sentido inválido");
            $this->assertContains($entrada['fuente_tipica'], Catalogo::FUENTES, "{$clave}: fuente fuera de la lista");
            $this->assertNotSame('', trim($entrada['descripcion']), $clave);
            // Sin benchmarks inventados: ninguna entrada puede traer meta.
            $this->assertArrayNotHasKey('meta', $entrada);
        }
    }

    public function test_incluye_las_metricas_estandar_con_el_sentido_esperado(): void
    {
        $esperado = [
            'impresiones' => 'mayor', 'clics' => 'mayor', 'ctr' => 'mayor', 'cpc' => 'menor', 'cpa' => 'menor',
            'roas' => 'mayor', 'conversiones' => 'mayor', 'inversion' => 'mayor', 'posicion_media' => 'menor',
            'keywords_top10' => 'mayor', 'sesiones_organicas' => 'mayor', 'llamadas' => 'mayor',
            'clics_whatsapp' => 'mayor', 'cotizaciones' => 'mayor', 'tasa_conversion' => 'mayor',
        ];

        foreach ($esperado as $clave => $sentido) {
            $this->assertSame($sentido, Catalogo::buscar($clave)['sentido'], $clave);
        }
        $this->assertCount(count($esperado), Catalogo::todos());
        $this->assertNull(Catalogo::buscar('inexistente'));
    }

    public function test_las_fuentes_del_catalogo_tienen_etiqueta(): void
    {
        $this->assertSame(Catalogo::FUENTES, array_keys(Catalogo::fuentes()));
    }

    public function test_la_inversion_sin_meta_da_semaforo_sin_meta(): void
    {
        $this->assertSame(Semaforo::SIN_META, Semaforo::evaluar(5000, null, Catalogo::sentido('inversion')));
    }
}
