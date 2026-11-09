<?php

namespace Tests\Unit\Support;

use App\Enums\TipoCobro;
use App\Enums\TipoServicio;
use App\Support\Metricas\Catalogo;
use App\Support\Plantillas\Giros;
use PHPUnit\Framework\TestCase;

/**
 * Catálogo estático de plantillas por giro: sus claves deben existir en los enums y el catálogo de métricas,
 * y nunca debe traer precios ni metas (no hay benchmarks verificables).
 */
class GirosTest extends TestCase
{
    public function test_hay_exactamente_cuatro_giros(): void
    {
        $this->assertSame(['hotel', 'servicios_profesionales', 'b2b_industrial', 'ecommerce'], Giros::claves());
        $this->assertSame(['hotel', 'servicios_profesionales', 'b2b_industrial', 'ecommerce'], array_keys(Giros::etiquetas()));
    }

    public function test_los_servicios_usan_tipos_y_cobros_validos_y_no_traen_precio(): void
    {
        foreach (Giros::todos() as $clave => $giro) {
            $this->assertSame($clave, $giro['clave']);
            $this->assertNotEmpty($giro['servicios'], $clave);
            foreach ($giro['servicios'] as $servicio) {
                $this->assertNotNull(TipoServicio::tryFrom($servicio['tipo']), "$clave: tipo {$servicio['tipo']}");
                $this->assertNotNull(TipoCobro::tryFrom($servicio['tipo_cobro']), "$clave: cobro {$servicio['tipo_cobro']}");
                $this->assertNotSame('', trim($servicio['nombre']));
                $this->assertArrayNotHasKey('precio_mensual', $servicio, 'las plantillas no inventan precios');
                $this->assertArrayNotHasKey('precio', $servicio);
            }
        }
    }

    public function test_las_metricas_existen_en_el_catalogo_y_no_llevan_meta(): void
    {
        foreach (Giros::todos() as $clave => $giro) {
            $this->assertNotEmpty($giro['metricas'], $clave);
            foreach ($giro['metricas'] as $metrica) {
                $this->assertContains($metrica, Catalogo::claves(), "$clave: métrica $metrica");
            }
            $this->assertSame(count($giro['metricas']), count(array_unique($giro['metricas'])), "$clave: métricas repetidas");
        }
    }

    public function test_los_marcadores_de_las_keywords_son_conocidos_y_el_tema_los_cubre(): void
    {
        foreach (Giros::todos() as $clave => $giro) {
            $this->assertContains($giro['tema']['marcador'], Giros::MARCADORES, $clave);
            foreach ($giro['keywords'] as $modelo) {
                preg_match_all('/\{(\w+)\}/', $modelo, $m);
                foreach ($m[1] as $marcador) {
                    $this->assertContains($marcador, Giros::MARCADORES, "$clave: {$modelo}");
                    // Cada marcador se alimenta de la ciudad del cliente o del tema propio del giro.
                    $this->assertContains($marcador, ['ciudad', $giro['tema']['marcador']], "$clave: $modelo");
                }
            }
            foreach ($giro['reportes'] as $reporte) {
                $this->assertContains($reporte, ['seo', 'ads', 'web']);
            }
        }
    }

    public function test_contenido_acordado_de_cada_giro(): void
    {
        $g = Giros::todos();

        $this->assertSame(['SEO local', 'Google Ads', 'Página web', '1 URL de SEO'], array_column($g['hotel']['servicios'], 'nombre'));
        $this->assertSame(['mensual', 'mensual', 'unico', 'gratuito'], array_column($g['hotel']['servicios'], 'tipo_cobro'));
        $this->assertSame(['seo', 'ads'], $g['hotel']['reportes']);
        $this->assertSame(['seo'], $g['servicios_profesionales']['reportes']);
        $this->assertSame(['Ficha de Google Business'], array_column(array_filter($g['servicios_profesionales']['servicios'], fn ($s) => $s['tipo_cobro'] === 'unico'), 'nombre'));
        $this->assertSame(['ads', 'seo'], $g['ecommerce']['reportes']);
        $this->assertContains('meta_ads', array_column($g['ecommerce']['servicios'], 'tipo'));
        $this->assertContains('roas', $g['ecommerce']['metricas']);
    }

    public function test_rellenar_sustituye_marcadores_y_omite_si_falta_el_dato(): void
    {
        $this->assertSame('hotel en aguascalientes', Giros::rellenar('hotel en {ciudad}', ['ciudad' => 'Aguascalientes']));
        $this->assertSame('dentista en león', Giros::rellenar('{servicio} en {ciudad}', ['servicio' => 'Dentista', 'ciudad' => 'León']));
        $this->assertNull(Giros::rellenar('hotel cerca de {atraccion}', ['ciudad' => 'X']));
        $this->assertNull(Giros::rellenar('hotel en {ciudad}', ['ciudad' => '   ']));
    }

    public function test_rellenar_neutraliza_separadores_que_partirian_la_linea_csv(): void
    {
        $this->assertSame('comprar tenis rojos', Giros::rellenar('comprar {producto}', ['producto' => "tenis,\trojos;"]));
    }

    public function test_buscar_devuelve_null_con_clave_desconocida_o_vacia(): void
    {
        $this->assertNull(Giros::buscar('inexistente'));
        $this->assertNull(Giros::buscar(null));
        $this->assertSame('Hotel', Giros::buscar('hotel')['etiqueta']);
    }
}
