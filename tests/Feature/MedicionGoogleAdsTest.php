<?php

namespace Tests\Feature;

use App\Support\Medicion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etiqueta global de Google Ads (gtag.js directo): solo en los entornos declarados, solo sin GTM
 * (con GTM se despliega desde el contenedor y cargarla dos veces duplicaría las mediciones),
 * con Consent Mode declarado antes de cargar nada y detrás del banner de cookies.
 */
class MedicionGoogleAdsTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'AW-18553224619';

    private function produccionSinGtm(?string $adsId = self::ID): void
    {
        config([
            'app.env' => 'production',
            'analytics.gtm_id' => null,
            'analytics.google_ads_id' => $adsId,
        ]);
    }

    public function test_en_produccion_sin_gtm_carga_gtag_de_google_ads(): void
    {
        $this->produccionSinGtm();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id='.self::ID, $html);
        $this->assertStringContainsString("gtag('config', \"".self::ID.'")', $html);
        $this->assertStringNotContainsString('gtm.js', $html, 'Sin GTM no se carga el contenedor.');
    }

    public function test_el_consentimiento_se_declara_antes_de_cargar_gtag(): void
    {
        $this->produccionSinGtm();

        $html = $this->get('/')->getContent();

        $this->assertNotFalse(strpos($html, "gtag('consent', 'default'"));
        $this->assertLessThan(
            strpos($html, 'gtag/js?id='),
            strpos($html, "gtag('consent', 'default'"),
            'Consent Mode debe declararse ANTES de cargar gtag.js.'
        );
        $this->assertStringContainsString('"ad_storage":"denied"', $this->compacto($html), 'Sin decision previa la publicidad nace denegada.');
    }

    public function test_la_etiqueta_directa_activa_el_banner_de_cookies(): void
    {
        $this->produccionSinGtm();

        $this->get('/')->assertSee('data-cookie-banner', false);
    }

    public function test_con_gtm_no_se_carga_la_etiqueta_directa_y_el_consentimiento_sale_una_vez(): void
    {
        config([
            'app.env' => 'production',
            'analytics.gtm_id' => 'GTM-XXXXXXX',
            'analytics.google_ads_id' => self::ID,
        ]);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('gtm.js', $html);
        $this->assertStringNotContainsString('gtag/js?id=', $html, 'Con GTM la etiqueta de Ads se despliega desde el contenedor.');
        $this->assertSame(1, substr_count($html, "gtag('consent', 'default'"));
    }

    public function test_fuera_de_los_entornos_declarados_no_se_emite_nada(): void
    {
        config(['app.env' => 'local', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => self::ID]);

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('data-cookie-banner', $html);
    }

    public function test_con_el_id_vacio_o_invalido_no_se_emite_nada(): void
    {
        foreach ([null, '', 'AW-<script>alert(1)</script>', 'G-ABC123', 'AW-12'] as $id) {
            $this->produccionSinGtm($id);

            $html = $this->get('/')->getContent();

            $this->assertStringNotContainsString('gtag/js', $html, 'ID no válido: '.var_export($id, true));
            $this->assertStringNotContainsString('data-cookie-banner', $html);
        }
    }

    public function test_el_login_del_panel_no_carga_medicion(): void
    {
        $this->produccionSinGtm();

        $this->get(route('login'))->assertDontSee('googletagmanager.com', false);
    }

    public function test_medicion_decide_segun_entorno_y_gtm(): void
    {
        $this->produccionSinGtm();
        $this->assertTrue(Medicion::adsDirectoActivo());
        $this->assertFalse(Medicion::gtmActivo());
        $this->assertTrue(Medicion::hayMedicion());

        config(['analytics.gtm_id' => 'GTM-XXXXXXX']);
        $this->assertFalse(Medicion::adsDirectoActivo());
        $this->assertTrue(Medicion::hayMedicion());

        config(['app.env' => 'staging']);
        $this->assertFalse(Medicion::hayMedicion());
    }

    /** HTML sin espacios ni saltos para comparar el JSON del consentimiento sin depender del formato. */
    private function compacto(string $html): string
    {
        return preg_replace('/\s+/', '', $html);
    }
}
