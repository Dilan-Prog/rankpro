<?php

namespace Tests\Feature;

use App\Support\Servicios;
use Tests\TestCase;

/**
 * Red de seguridad de SEO para las paginas publicas.
 *
 * Existe por un incidente concreto (ago-2026): el .env de produccion no tenia
 * APP_ENV=production, asi que las 13 URLs del sitio emitieron "noindex, nofollow"
 * durante meses y Google solo indexo 1 pagina. Nada en el codigo lo detecto.
 *
 * No necesita base de datos: el contenido publico sale de App\Support\Servicios.
 */
class SeoTest extends TestCase
{
    /** @return list<string> Todas las URLs publicas del sitio. */
    public static function urlsPublicas(): array
    {
        $urls = [
            '/', '/nosotros', '/contacto', '/servicios',
            '/terminos-y-condiciones', '/aviso-de-privacidad', '/politica-de-cookies',
        ];

        foreach (array_keys(Servicios::todos()) as $slug) {
            $urls[] = "/servicios/{$slug}";
        }

        return $urls;
    }

    /** @return array<string, array{string}> */
    public static function proveedorUrlsPublicas(): array
    {
        $urls = self::urlsPublicas();

        return array_combine($urls, array_map(static fn (string $url) => [$url], $urls));
    }

    // ------------------------------------------------------------------
    // Indexabilidad
    // ------------------------------------------------------------------

    /** @dataProvider proveedorUrlsPublicas */
    public function test_las_paginas_publicas_son_indexables_cuando_seo_indexable_es_true(string $url): void
    {
        config(['seo.indexable' => true]);

        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString(
            '<meta name="robots" content="index, follow',
            $html,
            "{$url} deberia ser indexable pero emite noindex. Revisa SEO_INDEXABLE y APP_ENV."
        );
    }

    /** @dataProvider proveedorUrlsPublicas */
    public function test_las_paginas_publicas_no_son_indexables_en_staging(string $url): void
    {
        config(['seo.indexable' => false]);

        $this->get($url)->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_seo_indexable_manda_sobre_app_env(): void
    {
        // Una copia mal configurada (APP_ENV=local en el servidor de produccion) no debe
        // desindexar si SEO_INDEXABLE dice explicitamente lo contrario, y viceversa.
        config(['app.env' => 'local', 'seo.indexable' => true]);
        $this->get('/')->assertSee('content="index, follow', false);

        config(['app.env' => 'production', 'seo.indexable' => false]);
        $this->get('/')->assertSee('content="noindex, nofollow"', false);
    }

    public function test_sin_seo_indexable_se_decide_por_app_env(): void
    {
        config(['seo.indexable' => null, 'app.env' => 'production']);
        $this->get('/')->assertSee('content="index, follow', false);

        config(['seo.indexable' => null, 'app.env' => 'local']);
        $this->get('/')->assertSee('content="noindex, nofollow"', false);
    }

    // ------------------------------------------------------------------
    // Canonicas
    // ------------------------------------------------------------------

    /** @dataProvider proveedorUrlsPublicas */
    public function test_cada_pagina_declara_exactamente_una_canonica(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match_all('/<link rel="canonical"/', $html),
            "{$url} debe declarar exactamente una etiqueta canonical."
        );
    }

    public function test_la_canonica_de_la_portada_lleva_barra_final(): void
    {
        $html = $this->get('/')->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);

        $this->assertNotEmpty($m, 'La portada no declara canonical.');
        $this->assertStringEndsWith(
            '/',
            $m[1],
            'La canonica de la portada debe terminar en barra: la URL real es "/" y '
            .'url()->current() devuelve el origen sin ella.'
        );
    }

    /** @dataProvider proveedorUrlsPublicas */
    public function test_la_canonica_es_absoluta(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);

        $this->assertStringStartsWith('http', $m[1] ?? '', "Canonica no absoluta en {$url}.");
    }

    // ------------------------------------------------------------------
    // Metadatos
    // ------------------------------------------------------------------

    /** @dataProvider proveedorUrlsPublicas */
    public function test_titulo_dentro_del_limite_de_la_serp(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $t);
        $this->assertNotEmpty($t[1] ?? '', "{$url} no tiene <title>.");

        $titulo = html_entity_decode($t[1], ENT_QUOTES, 'UTF-8');

        $this->assertLessThanOrEqual(
            60,
            mb_strlen($titulo),
            "El title de {$url} pasa de 60 caracteres y Google lo truncara ("
            .mb_strlen($titulo)."): \"{$titulo}\""
        );
    }

    /** @dataProvider proveedorUrlsPublicas */
    public function test_descripcion_dentro_del_limite_de_la_serp(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match('/<meta name="description" content="([^"]*)"/', $html, $d);
        $this->assertNotEmpty($d[1] ?? '', "{$url} no tiene meta description.");

        $desc = html_entity_decode($d[1], ENT_QUOTES, 'UTF-8');

        $this->assertLessThanOrEqual(
            160,
            mb_strlen($desc),
            "La description de {$url} pasa de 160 caracteres ("
            .mb_strlen($desc)."): \"{$desc}\""
        );
        $this->assertGreaterThanOrEqual(
            70,
            mb_strlen($desc),
            "La description de {$url} es demasiado corta para la SERP: \"{$desc}\""
        );
    }

    /** @dataProvider proveedorUrlsPublicas */
    public function test_cada_pagina_tiene_un_solo_h1(string $url): void
    {
        $html = $this->get($url)->getContent();

        $this->assertSame(
            1,
            preg_match_all('/<h1[\s>]/i', $html),
            "{$url} debe tener exactamente un <h1>."
        );
    }

    /** @dataProvider proveedorUrlsPublicas */
    public function test_open_graph_completo(string $url): void
    {
        $html = $this->get($url)->getContent();

        foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:type'] as $prop) {
            $this->assertStringContainsString(
                "property=\"{$prop}\"",
                $html,
                "Falta {$prop} en {$url}."
            );
        }
    }

    public function test_og_type_por_defecto_es_website(): void
    {
        // Contrato de meta.blade.php: las paginas que no declaran @section('og_type')
        // siguen emitiendo "website". Blindado para que anadir el blog no las cambie.
        $this->get('/servicios')->assertSee('<meta property="og:type" content="website">', false);
    }

    // ------------------------------------------------------------------
    // Sitemap
    // ------------------------------------------------------------------

    public function test_el_sitemap_incluye_todas_las_paginas_publicas(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (self::urlsPublicas() as $url) {
            $this->assertStringContainsString(
                url($url),
                $xml,
                "El sitemap no incluye {$url}."
            );
        }
    }

    public function test_el_sitemap_no_incluye_urls_bloqueadas_en_robots(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        foreach (['/admin', '/login', '/register'] as $bloqueada) {
            $this->assertStringNotContainsString(
                '<loc>'.url($bloqueada),
                $xml,
                "El sitemap no debe listar {$bloqueada}: robots.txt lo bloquea."
            );
        }
    }

    // ------------------------------------------------------------------
    // Datos estructurados
    // ------------------------------------------------------------------

    /** @dataProvider proveedorUrlsPublicas */
    public function test_el_json_ld_es_json_valido(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        $this->assertNotEmpty($m[1], "{$url} no emite ningun bloque JSON-LD.");

        foreach ($m[1] as $i => $json) {
            $this->assertNotNull(
                json_decode($json),
                "Bloque JSON-LD #{$i} invalido en {$url}: ".json_last_error_msg()
            );
        }
    }

    // ------------------------------------------------------------------
    // Errores
    // ------------------------------------------------------------------

    public function test_una_url_inexistente_devuelve_404_y_no_200(): void
    {
        // Un 404 servido con estado 200 ("soft 404") hace que Google indexe
        // paginas de error y desperdicie presupuesto de rastreo.
        $this->get('/esta-url-no-existe-'.uniqid())->assertNotFound();
    }

    public function test_el_404_declara_noindex_aunque_el_sitio_sea_indexable(): void
    {
        config(['seo.indexable' => true]);

        $html = $this->get('/esta-url-no-existe-'.uniqid())->getContent();

        $this->assertStringContainsString('content="noindex, follow"', $html,
            'El 404 debe declarar noindex explicito via @section("robots").');
    }

    public function test_el_override_de_robots_no_puede_indexar_en_staging(): void
    {
        // Una pagina no debe poder forzar "index" cuando el sitio entero esta
        // marcado como no indexable: el noindex global siempre gana.
        config(['seo.indexable' => false]);

        $this->get('/esta-url-no-existe-'.uniqid())
            ->assertSee('content="noindex, nofollow"', false);
    }

    // ------------------------------------------------------------------
    // Medicion
    // ------------------------------------------------------------------

    public function test_sin_gtm_id_no_se_emite_ningun_script_de_medicion(): void
    {
        config(['analytics.gtm_id' => null]);

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('data-cookie-banner', $html,
            'Sin medicion no hay nada que consentir: el banner no debe aparecer.');
    }

    public function test_gtm_no_se_activa_fuera_de_los_entornos_declarados(): void
    {
        // Evita que una copia del .env en staging mande datos a la propiedad real.
        config(['analytics.gtm_id' => 'GTM-XXXXXXX', 'app.env' => 'staging']);

        $this->get('/')->assertDontSee('googletagmanager.com', false);
    }

    public function test_en_produccion_gtm_carga_con_consent_mode_declarado_antes(): void
    {
        config(['analytics.gtm_id' => 'GTM-XXXXXXX', 'app.env' => 'production']);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('GTM-XXXXXXX', $html);
        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        $this->assertStringContainsString('data-cookie-banner', $html);

        // El orden importa: si GTM carga antes de declarar el consentimiento por
        // defecto, las primeras etiquetas salen sin senal y Google las descarta.
        $this->assertLessThan(
            strpos($html, 'googletagmanager.com/gtm.js'),
            strpos($html, "gtag('consent', 'default'"),
            'Consent Mode debe declararse ANTES de cargar el contenedor de GTM.'
        );
    }
}
