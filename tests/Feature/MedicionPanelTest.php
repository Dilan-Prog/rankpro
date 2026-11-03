<?php

namespace Tests\Feature;

use App\Models\ConfiguracionMedicion;
use App\Support\Medicion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Medición configurada desde el panel (fila única configuracion_medicion): manda sobre el .env,
 * emite solo si está activa y el host está permitido, e ignora APP_ENV (el servidor de
 * producción corre con APP_ENV=local).
 */
class MedicionPanelTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'rankprosolutions.com.mx';

    private function panel(array $atributos = []): ConfiguracionMedicion
    {
        return ConfiguracionMedicion::query()->forceCreate($atributos + [
            'id' => 1,
            'activa' => true,
            'dominios' => self::HOST,
            'gtm_id' => null,
            'ga4_id' => null,
            'google_ads_id' => null,
        ]);
    }

    private function portada(string $host = self::HOST): string
    {
        return $this->get('http://'.$host.'/')->getContent();
    }

    // ------------------------------------------------------------------
    // Activación
    // ------------------------------------------------------------------

    public function test_activa_con_host_permitido_emite_aunque_app_env_sea_local(): void
    {
        config(['app.env' => 'local', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => null]);
        $this->panel(['google_ads_id' => 'AW-18553224619']);

        $html = $this->portada();

        $this->assertStringContainsString('gtag/js?id=AW-18553224619', $html);
        $this->assertStringContainsString('data-cookie-banner', $html);
        $this->assertSame('panel', Medicion::fuente());
    }

    public function test_desactivada_no_emite_nada_aunque_el_env_tenga_ids(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => 'GTM-XXXXXXX', 'analytics.google_ads_id' => 'AW-18553224619']);
        $this->panel(['activa' => false, 'gtm_id' => 'GTM-ABC1234']);

        $html = $this->portada();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('data-cookie-banner', $html);
        $this->assertStringNotContainsString('rankproMedicion', $html);
    }

    public function test_host_no_permitido_no_emite_y_el_motivo_lo_nombra(): void
    {
        $this->panel(['google_ads_id' => 'AW-18553224619']);

        $html = $this->portada('staging.ejemplo.com');
        $estado = Medicion::estado('staging.ejemplo.com');

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertFalse($estado['activa']);
        $this->assertNull($estado['modo']);
        $this->assertStringContainsString('staging.ejemplo.com', implode(' ', $estado['motivos']));
    }

    public function test_www_y_sin_www_cuentan_como_el_mismo_host(): void
    {
        $this->panel(['google_ads_id' => 'AW-18553224619', 'dominios' => 'www.rankprosolutions.com.mx']);
        $this->assertStringContainsString('gtag/js', $this->portada('rankprosolutions.com.mx'));

        ConfiguracionMedicion::query()->find(1)->update(['dominios' => 'rankprosolutions.com.mx']);
        $this->assertStringContainsString('gtag/js', $this->portada('www.rankprosolutions.com.mx'));
    }

    public function test_varios_dominios_por_coma_o_salto_de_linea(): void
    {
        $this->panel(['google_ads_id' => 'AW-18553224619', 'dominios' => "otro.com,\nHTTPS://Mi.Sitio.mx:8080/ruta"]);

        $this->assertStringContainsString('gtag/js', $this->portada('otro.com'));
        $this->assertStringContainsString('gtag/js', $this->portada('mi.sitio.mx'));
        $this->assertStringNotContainsString('gtag/js', $this->portada('rankprosolutions.com.mx'));
    }

    public function test_sin_dominios_configurados_no_emite(): void
    {
        $this->panel(['google_ads_id' => 'AW-18553224619', 'dominios' => '']);

        $this->assertStringNotContainsString('gtag/js', $this->portada());
        $this->assertStringContainsString('No hay dominios permitidos', implode(' ', Medicion::estado(self::HOST)['motivos']));
    }

    // ------------------------------------------------------------------
    // gtm vs gtag
    // ------------------------------------------------------------------

    public function test_con_gtm_no_hay_loader_directo_y_el_consentimiento_sale_una_vez(): void
    {
        $this->panel(['gtm_id' => 'GTM-ABC1234', 'ga4_id' => 'G-ABCDE12345', 'google_ads_id' => 'AW-18553224619']);

        $html = $this->portada();

        $this->assertStringContainsString('gtm.js', $html);
        $this->assertStringContainsString('ns.html?id=GTM-ABC1234', $html);
        $this->assertStringNotContainsString('gtag/js?id=', $html);
        $this->assertSame(1, substr_count($html, "gtag('consent', 'default'"));
        $this->assertSame('gtm', Medicion::modo());

        $estado = Medicion::estado(self::HOST);
        $this->assertSame(['gtm' => true, 'ga4' => false, 'ads' => false, 'banner' => true], $estado['emite']);
        $this->assertStringContainsString('Tag Manager', implode(' ', $estado['motivos']));
    }

    public function test_sin_gtm_un_solo_loader_con_ga4_y_config_para_ambos_ids(): void
    {
        $this->panel(['ga4_id' => 'G-ABCDE12345', 'google_ads_id' => 'AW-18553224619']);

        $html = $this->portada();

        $this->assertSame(1, substr_count($html, 'gtag/js?id='));
        $this->assertStringContainsString('gtag/js?id=G-ABCDE12345', $html, 'GA4 abre el loader cuando existe.');
        $this->assertStringContainsString("gtag('config', \"G-ABCDE12345\")", $html);
        $this->assertStringContainsString("gtag('config', \"AW-18553224619\")", $html);
        $this->assertSame(1, substr_count($html, "gtag('consent', 'default'"));
        $this->assertStringNotContainsString('gtm.js', $html);
        $this->assertSame('gtag', Medicion::modo());
        $this->assertTrue(Medicion::gtagActivo());
        $this->assertFalse(Medicion::gtmActivo());

        $this->assertSame(['gtm' => false, 'ga4' => true, 'ads' => true, 'banner' => true], Medicion::estado(self::HOST)['emite']);
    }

    public function test_solo_ads(): void
    {
        $this->panel(['google_ads_id' => 'AW-18553224619']);

        $html = $this->portada();

        $this->assertSame(1, substr_count($html, 'gtag/js?id='));
        $this->assertStringContainsString('gtag/js?id=AW-18553224619', $html);
        $this->assertSame(1, substr_count($html, "gtag('config'"));
        $this->assertSame(['gtm' => false, 'ga4' => false, 'ads' => true, 'banner' => true], Medicion::estado(self::HOST)['emite']);
    }

    public function test_solo_ga4(): void
    {
        $this->panel(['ga4_id' => 'G-ABCDE12345']);

        $html = $this->portada();

        $this->assertSame(1, substr_count($html, 'gtag/js?id='));
        $this->assertStringContainsString("gtag('config', \"G-ABCDE12345\")", $html);
        $this->assertStringNotContainsString('AW-', $html);
        $this->assertSame(['gtm' => false, 'ga4' => true, 'ads' => false, 'banner' => true], Medicion::estado(self::HOST)['emite']);
    }

    public function test_un_id_invalido_en_la_bd_no_se_imprime(): void
    {
        $this->panel(['gtm_id' => 'GTM-<script>', 'google_ads_id' => "AW-1'));alert(1);//"]);

        $html = $this->portada();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertNull(Medicion::modo());

        $motivos = implode(' ', Medicion::estado(self::HOST)['motivos']);
        $this->assertStringContainsString('no tiene el formato', $motivos);
    }

    public function test_sin_ningun_id_valido_el_motivo_lo_dice(): void
    {
        $this->panel();

        $estado = Medicion::estado(self::HOST);

        $this->assertNull($estado['modo']);
        $this->assertStringContainsString('No hay ningún ID válido', implode(' ', $estado['motivos']));
    }

    // ------------------------------------------------------------------
    // window.rankproMedicion + banner
    // ------------------------------------------------------------------

    public function test_expone_window_rankpro_medicion_con_modo_y_conversiones_validas(): void
    {
        $this->panel([
            'google_ads_id' => 'AW-18553224619',
            'conversion_agendar' => 'AW-18553224619/AbC_dEfGhIj',
            'conversion_whatsapp' => 'basura<script>',
        ]);

        $html = $this->portada();

        $this->assertSame(1, preg_match('/window\.rankproMedicion = (\{.*?\});<\/script>/s', $html, $m));
        $this->assertSame([
            'modo' => 'gtag',
            'conversiones' => [
                'agendar_completado' => 'AW-18553224619/AbC_dEfGhIj',
                'click_whatsapp' => null,
                'click_telefono' => null,
            ],
        ], json_decode($m[1], true));
        $this->assertStringNotContainsString('basura', $html);
    }

    public function test_rankpro_medicion_en_modo_gtm(): void
    {
        $this->panel(['gtm_id' => 'GTM-ABC1234']);

        preg_match('/window\.rankproMedicion = (\{.*?\});<\/script>/s', $this->portada(), $m);

        $this->assertSame('gtm', json_decode($m[1], true)['modo']);
    }

    public function test_sin_medicion_no_hay_rankpro_medicion_ni_banner(): void
    {
        $this->panel(['activa' => false, 'google_ads_id' => 'AW-18553224619']);

        $html = $this->portada();

        $this->assertStringNotContainsString('rankproMedicion', $html);
        $this->assertStringNotContainsString('data-cookie-banner', $html);
        $this->assertFalse(Medicion::hayMedicion());
    }

    public function test_conversion_por_evento(): void
    {
        $this->panel(['conversion_telefono' => 'aw-18553224619/Tel_12345', 'conversion_agendar' => 'AW-1/x']);

        $this->assertSame('AW-18553224619/Tel_12345', Medicion::conversion('click_telefono'));
        $this->assertNull(Medicion::conversion('agendar_completado'), 'Formato inválido => null.');
        $this->assertNull(Medicion::conversion('click_whatsapp'));
        $this->assertNull(Medicion::conversion('evento_inexistente'));
    }

    // ------------------------------------------------------------------
    // Modo .env intacto y caché
    // ------------------------------------------------------------------

    public function test_sin_fila_se_comporta_como_antes_con_el_env(): void
    {
        config(['app.env' => 'production', 'analytics.gtm_id' => null, 'analytics.google_ads_id' => 'AW-18553224619']);

        $this->assertSame('env', Medicion::fuente());
        $this->assertSame('gtag', Medicion::modo());
        $this->assertStringContainsString('gtag/js?id=AW-18553224619', $this->portada('cualquier-host.test'), 'En modo .env el host no importa.');

        config(['app.env' => 'local']);
        $this->assertNull(Medicion::modo());
        $this->assertStringContainsString('APP_ENV es «local»', implode(' ', Medicion::estado()['motivos']));
    }

    public function test_la_ausencia_de_fila_tambien_se_cachea_y_se_invalida_al_crearla(): void
    {
        $this->assertNull(ConfiguracionMedicion::actual());
        $this->assertSame('sin-fila', Cache::get(ConfiguracionMedicion::CACHE_CLAVE), 'Se recuerda el "no hay fila" con un marcador.');

        $this->panel(['google_ads_id' => 'AW-18553224619']);

        $this->assertNotNull(ConfiguracionMedicion::actual());
    }

    public function test_guardar_y_eliminar_olvidan_la_cache(): void
    {
        $config = $this->panel(['google_ads_id' => 'AW-18553224619']);
        $this->assertSame('AW-18553224619', ConfiguracionMedicion::actual()->google_ads_id);

        $config->update(['google_ads_id' => 'AW-999999999']);
        $this->assertSame('AW-999999999', ConfiguracionMedicion::actual()->google_ads_id);

        $config->delete();
        $this->assertNull(ConfiguracionMedicion::actual());
        $this->assertSame('env', Medicion::fuente());
    }
}
