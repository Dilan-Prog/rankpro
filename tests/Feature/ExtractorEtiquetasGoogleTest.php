<?php

namespace Tests\Feature;

use App\Support\ExtractorEtiquetasGoogle;
use App\Support\FormatosGoogle;
use Tests\TestCase;

/**
 * El dueño pega el bloque que le da Google; el extractor se queda solo con los IDs que cumplen
 * los formatos (lista blanca de lo que luego se imprime en el HTML público).
 */
class ExtractorEtiquetasGoogleTest extends TestCase
{
    private const GTAG_ADS = <<<'HTML'
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18553224619"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-18553224619');
</script>
HTML;

    private const GTM = <<<'HTML'
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-ABC1234');</script>
<!-- End Google Tag Manager -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC1234"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
HTML;

    private const GA4 = <<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABCDE12345"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-ABCDE12345');
</script>
HTML;

    public function test_extrae_el_id_de_ads_del_gtag_de_google_ads(): void
    {
        $r = ExtractorEtiquetasGoogle::desde(self::GTAG_ADS);

        $this->assertSame('AW-18553224619', $r['ads']);
        $this->assertNull($r['gtm']);
        $this->assertNull($r['ga4']);
        $this->assertSame([], $r['conversiones']);
    }

    public function test_extrae_el_id_de_gtm_del_snippet_de_tag_manager(): void
    {
        $r = ExtractorEtiquetasGoogle::desde(self::GTM);

        $this->assertSame('GTM-ABC1234', $r['gtm']);
        $this->assertNull($r['ga4']);
        $this->assertNull($r['ads']);
    }

    public function test_extrae_el_id_de_ga4(): void
    {
        $r = ExtractorEtiquetasGoogle::desde(self::GA4);

        $this->assertSame('G-ABCDE12345', $r['ga4']);
        $this->assertNull($r['gtm']);
        $this->assertNull($r['ads']);
    }

    public function test_extrae_varios_tipos_de_un_solo_pegado_y_las_conversiones_de_send_to(): void
    {
        $texto = self::GA4."\n".self::GTAG_ADS."\n"
            ."<script>gtag('event', 'conversion', {'send_to': 'AW-18553224619/AbC_dEfGhIj'});</script>\n"
            .'<script>gtag("event","conversion",{send_to:"AW-18553224619/Zz9-yXwVuTs",value:1});</script>';

        $r = ExtractorEtiquetasGoogle::desde($texto);

        $this->assertSame('G-ABCDE12345', $r['ga4']);
        $this->assertSame('AW-18553224619', $r['ads']);
        $this->assertSame(['AW-18553224619/AbC_dEfGhIj', 'AW-18553224619/Zz9-yXwVuTs'], $r['conversiones']);
    }

    public function test_si_hay_varios_del_mismo_tipo_toma_el_primero(): void
    {
        $r = ExtractorEtiquetasGoogle::desde("GTM-AAAA1111 y luego GTM-BBBB2222 ; G-AAAAAA1111 G-BBBBBB2222 ; AW-111111111 AW-222222222");

        $this->assertSame('GTM-AAAA1111', $r['gtm']);
        $this->assertSame('G-AAAAAA1111', $r['ga4']);
        $this->assertSame('AW-111111111', $r['ads']);
    }

    public function test_la_cuenta_de_ads_tambien_sale_de_una_etiqueta_de_conversion(): void
    {
        $r = ExtractorEtiquetasGoogle::desde("gtag('event','conversion',{'send_to':'AW-987654321/AbCdEf_123'})");

        $this->assertSame('AW-987654321', $r['ads']);
        $this->assertSame(['AW-987654321/AbCdEf_123'], $r['conversiones']);
    }

    public function test_ignora_ids_con_formato_invalido_y_texto_cualquiera(): void
    {
        $r = ExtractorEtiquetasGoogle::desde("GTM-ab AW-12 G-ABC <script>alert(1)</script> UA-12345-1 mi-g-tag");

        $this->assertSame(['gtm' => null, 'ga4' => null, 'ads' => null, 'conversiones' => []], $r);
        $this->assertSame(['gtm' => null, 'ga4' => null, 'ads' => null, 'conversiones' => []], ExtractorEtiquetasGoogle::desde(''));
    }

    public function test_una_conversion_fuera_de_send_to_no_cuenta(): void
    {
        $r = ExtractorEtiquetasGoogle::desde('<p>AW-111111111/AbCdEf_123</p>');

        $this->assertSame([], $r['conversiones']);
        $this->assertSame('AW-111111111', $r['ads']);
    }

    public function test_el_resultado_solo_contiene_ids_validos_nunca_el_texto_pegado(): void
    {
        $r = ExtractorEtiquetasGoogle::desde(self::GTM.self::GA4.self::GTAG_ADS);

        $this->assertStringNotContainsString('<script', json_encode($r));
        $this->assertSame(1, preg_match(FormatosGoogle::GTM, $r['gtm']));
        $this->assertSame(1, preg_match(FormatosGoogle::GA4, $r['ga4']));
        $this->assertSame(1, preg_match(FormatosGoogle::ADS, $r['ads']));
    }

    public function test_formatos_validan_y_normalizan(): void
    {
        $this->assertSame('GTM-ABC1234', FormatosGoogle::gtm('  gtm-abc1234 '));
        $this->assertNull(FormatosGoogle::gtm('GTM-AB'));
        $this->assertNull(FormatosGoogle::gtm('GTM-ABCDEFGHIJK'));
        $this->assertNull(FormatosGoogle::gtm(null));

        $this->assertSame('G-ABCDE12345', FormatosGoogle::ga4('g-abcde12345'));
        $this->assertNull(FormatosGoogle::ga4('G-ABC'));
        $this->assertNull(FormatosGoogle::ga4('UA-12345-1'));

        $this->assertSame('AW-123456', FormatosGoogle::ads('aw-123456'));
        $this->assertNull(FormatosGoogle::ads('AW-12345'));
        $this->assertNull(FormatosGoogle::ads('AW-1234567890123456'));
        $this->assertNull(FormatosGoogle::ads('AW-<script>'));

        // La etiqueta distingue mayúsculas: solo se normaliza el prefijo de la cuenta.
        $this->assertSame('AW-123456789/AbC_dEfGhIj', FormatosGoogle::conversion(' aw-123456789/AbC_dEfGhIj '));
        $this->assertNull(FormatosGoogle::conversion('AW-123456789'));
        $this->assertNull(FormatosGoogle::conversion('AW-123456789/ab'));
        $this->assertNull(FormatosGoogle::conversion('AW-123456789/ab cd ef'));
    }
}
