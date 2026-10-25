<?php

namespace Tests\Unit\Services;

use App\Services\SearchConsole\ClienteSearchConsole;
use App\Services\SearchConsole\ErrorSearchConsole;
use App\Support\SearchConsole\NormalizadorKeyword;
use App\Support\SearchConsole\Saneador;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\LlaveGoogleDePrueba;
use Tests\TestCase;

/**
 * Cliente HTTP de Search Console: firma JWT, caché del token, paginación y traducción de errores.
 * Todo con Http::fake: ninguna petición sale a Google.
 */
class SearchConsoleClienteTest extends TestCase
{
    private const SITIOS = 'https://www.googleapis.com/webmasters/v3/sites';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        // Sin esperas reales entre reintentos.
        config(['services.google_search_console.espera_ms' => 0]);
    }

    private function cliente(): ClienteSearchConsole
    {
        return new ClienteSearchConsole([
            'client_email' => LlaveGoogleDePrueba::CORREO,
            'private_key' => LlaveGoogleDePrueba::par()['privada'],
        ]);
    }

    private function tokenOk(int $expiraEn = 3600): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['access_token' => 'ya29.token-de-prueba', 'expires_in' => $expiraEn, 'token_type' => 'Bearer']);
    }

    private function b64url(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'));
    }

    public function test_el_jwt_esta_firmado_rs256_y_se_verifica_con_la_clave_publica(): void
    {
        $jwt = $this->cliente()->firmarJwt(1_800_000_000);
        [$cabecera, $reclamos, $firma] = explode('.', $jwt);

        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($this->b64url($cabecera), true));

        $datos = json_decode($this->b64url($reclamos), true);
        $this->assertSame(LlaveGoogleDePrueba::CORREO, $datos['iss']);
        $this->assertSame('https://www.googleapis.com/auth/webmasters.readonly', $datos['scope']);
        $this->assertSame(self::TOKEN, $datos['aud']);
        $this->assertSame(1_800_000_000, $datos['iat']);
        $this->assertSame(1_800_003_600, $datos['exp']);

        $publica = openssl_pkey_get_public(LlaveGoogleDePrueba::par()['publica']);
        $this->assertSame(1, openssl_verify("{$cabecera}.{$reclamos}", $this->b64url($firma), $publica, OPENSSL_ALGO_SHA256));

        // Y una firma alterada NO verifica: la prueba anterior no es vacua.
        $this->assertSame(0, openssl_verify("{$cabecera}.{$reclamos}x", $this->b64url($firma), $publica, OPENSSL_ALGO_SHA256));
    }

    public function test_una_llave_privada_corrupta_da_un_error_claro_sin_filtrarla(): void
    {
        $cliente = new ClienteSearchConsole(['client_email' => LlaveGoogleDePrueba::CORREO, 'private_key' => '-----BEGIN PRIVATE KEY-----basura-----END PRIVATE KEY-----']);

        try {
            $cliente->firmarJwt();
            $this->fail('Debió lanzar ErrorSearchConsole');
        } catch (ErrorSearchConsole $e) {
            $this->assertStringContainsString('llave privada', $e->getMessage());
            $this->assertStringNotContainsString('basura', $e->getMessage());
        }
    }

    public function test_el_token_se_pide_con_el_grant_jwt_bearer_y_se_cachea(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk()]);
        $cliente = $this->cliente();

        $this->assertSame('ya29.token-de-prueba', $cliente->token());
        $this->assertSame('ya29.token-de-prueba', $cliente->token());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            $this->assertSame(self::TOKEN, $r->url());
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $r['grant_type']);
            $this->assertCount(3, explode('.', $r['assertion']));

            return true;
        });
    }

    public function test_el_token_cacheado_no_se_guarda_en_claro(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk()]);
        $this->cliente()->token();

        $almacen = new \ReflectionProperty(Cache::getStore(), 'storage');
        $almacen->setAccessible(true);
        $guardados = serialize($almacen->getValue(Cache::getStore()));

        $this->assertNotSame('', $guardados);
        $this->assertStringContainsString('gsc:token:', $guardados);
        $this->assertStringNotContainsString('ya29.token-de-prueba', $guardados);
    }

    public function test_el_token_se_renueva_cuando_esta_por_expirar(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        Http::fake([self::TOKEN => $this->tokenOk(3600)]);
        $cliente = $this->cliente();

        $cliente->token();
        // Vive 3600 - 120 s: a los 3000 s sigue en caché, a los 3500 s ya no.
        Carbon::setTestNow('2026-10-26 10:50:00');
        $cliente->token();
        Http::assertSentCount(1);

        Carbon::setTestNow('2026-10-26 10:58:20');
        $cliente->token();
        Http::assertSentCount(2);

        Carbon::setTestNow();
    }

    public function test_si_google_rechaza_la_llave_el_mensaje_es_claro_y_no_incluye_la_llave(): void
    {
        $privada = LlaveGoogleDePrueba::par()['privada'];
        Http::fake([self::TOKEN => Http::response(['error' => 'invalid_grant', 'error_description' => "Invalid JWT Signature. {$privada} en C:\\laragon\\www\\rankpro\\x.php"], 400)]);

        try {
            $this->cliente()->token();
            $this->fail('Debió lanzar ErrorSearchConsole');
        } catch (ErrorSearchConsole $e) {
            $this->assertStringContainsString('rechazó la llave', $e->getMessage());
            $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $e->getMessage());
            $this->assertStringNotContainsString('laragon', $e->getMessage());
        }
    }

    public function test_propiedades_lista_los_sitios_accesibles(): void
    {
        Http::fake([
            self::TOKEN => $this->tokenOk(),
            self::SITIOS => Http::response(['siteEntry' => [
                ['siteUrl' => 'https://ejemplo.com/', 'permissionLevel' => 'siteRestrictedUser'],
                ['siteUrl' => 'sc-domain:otro.com', 'permissionLevel' => 'siteOwner'],
            ]]),
        ]);

        $this->assertSame([
            ['site_url' => 'https://ejemplo.com/', 'permiso' => 'siteRestrictedUser'],
            ['site_url' => 'sc-domain:otro.com', 'permiso' => 'siteOwner'],
        ], $this->cliente()->propiedades());

        Http::assertSent(fn (Request $r) => $r->url() === self::SITIOS && $r->hasHeader('Authorization', 'Bearer ya29.token-de-prueba'));
    }

    public function test_consultar_parsea_filas_y_pagina_con_start_row(): void
    {
        Http::fake([
            self::TOKEN => $this->tokenOk(),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::sequence()
                ->push(['rows' => [
                    ['keys' => ['hotel en ags'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 3.4],
                    ['keys' => ['hoteles'], 'clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 12.0],
                ]])
                ->push(['rows' => [
                    ['keys' => ['cuartos'], 'clicks' => 0, 'impressions' => 5, 'ctr' => 0, 'position' => 40.2],
                ]]),
        ]);

        $filas = $this->cliente()->consultar('sc-domain:ejemplo.com', '2026-09-01', '2026-09-30', ['query'], 'final', 10, 2);

        $this->assertCount(3, $filas);
        $this->assertSame(['keys' => ['hotel en ags'], 'clics' => 10, 'impresiones' => 100, 'ctr' => 0.1, 'posicion' => 3.4], $filas[0]);
        $this->assertSame(40.2, $filas[2]['posicion']);

        $consultas = Http::recorded(fn (Request $r) => str_contains($r->url(), 'searchAnalytics'))->map(fn ($par) => $par[0])->values();
        $this->assertCount(2, $consultas);
        $this->assertSame(0, $consultas[0]['startRow']);
        $this->assertSame(2, $consultas[1]['startRow']);
        $this->assertSame(2, $consultas[0]['rowLimit']);
        $this->assertSame(['query'], $consultas[0]['dimensions']);
        $this->assertSame('final', $consultas[0]['dataState']);
        $this->assertSame('2026-09-01', $consultas[0]['startDate']);
        // siteUrl codificado en la ruta (los dos puntos de sc-domain: no van sueltos).
        $this->assertStringContainsString('/sites/sc-domain%3Aejemplo.com/searchAnalytics/query', $consultas[0]->url());
    }

    public function test_consultar_con_url_de_prefijo_codifica_las_barras(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response(['rows' => []])]);

        $this->assertSame([], $this->cliente()->consultar('https://ejemplo.com/', '2026-09-01', '2026-09-30', ['date']));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sites/https%3A%2F%2Fejemplo.com%2F/searchAnalytics/query'));
    }

    public function test_consultar_respeta_el_tope_de_paginas(): void
    {
        $pagina = ['rows' => [['keys' => ['a'], 'clicks' => 1, 'impressions' => 1, 'ctr' => 1, 'position' => 1]]];
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response($pagina)]);

        $filas = $this->cliente()->consultar('sc-domain:e.com', '2026-09-01', '2026-09-30', ['query'], 'final', 3, 1);

        $this->assertCount(3, $filas);
    }

    /** @return array<string, array{0: int, 1: array, 2: string}> */
    public static function erroresDeGoogle(): array
    {
        return [
            '401' => [401, ['error' => ['message' => 'Invalid Credentials']], 'no tiene acceso a la propiedad sc-domain:e.com'],
            '403' => [403, ['error' => ['message' => 'User does not have sufficient permission', 'errors' => [['reason' => 'forbidden']]]], 'no tiene acceso a la propiedad sc-domain:e.com'],
            '404' => [404, ['error' => ['message' => 'Not Found']], 'no encontró la propiedad sc-domain:e.com'],
            '400' => [400, ['error' => ['message' => 'Invalid value at dimensions']], 'rechazó la consulta (400): Invalid value at dimensions'],
        ];
    }

    /** @dataProvider erroresDeGoogle */
    public function test_los_errores_de_google_se_traducen_a_espanol(int $estado, array $cuerpo, string $esperado): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response($cuerpo, $estado)]);

        try {
            $this->cliente()->consultar('sc-domain:e.com', '2026-09-01', '2026-09-30', ['query']);
            $this->fail('Debió lanzar ErrorSearchConsole');
        } catch (ErrorSearchConsole $e) {
            $this->assertStringContainsString($esperado, $e->getMessage());
            $this->assertSame($estado, $e->estadoHttp);
            // Los 4xx no se reintentan: token + una sola consulta.
            Http::assertSentCount(2);
        }
    }

    public function test_el_403_incluye_el_correo_a_dar_de_alta(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response(['error' => ['message' => 'x']], 403)]);

        try {
            $this->cliente()->consultar('sc-domain:e.com', '2026-09-01', '2026-09-30', ['query']);
            $this->fail('Debió lanzar ErrorSearchConsole');
        } catch (ErrorSearchConsole $e) {
            $this->assertStringContainsString(LlaveGoogleDePrueba::CORREO, $e->getMessage());
            $this->assertStringContainsString('Restringido', $e->getMessage());
        }
    }

    public function test_api_deshabilitada_en_el_proyecto_tiene_su_propio_mensaje(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response(['error' => ['message' => 'API has not been used', 'errors' => [['reason' => 'accessNotConfigured']]]], 403)]);

        $this->expectException(ErrorSearchConsole::class);
        $this->expectExceptionMessage('no está habilitada');

        $this->cliente()->propiedades();
    }

    public function test_un_401_descarta_el_token_cacheado(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response(['error' => ['message' => 'x']], 401)]);
        $cliente = $this->cliente();

        try {
            $cliente->propiedades();
        } catch (ErrorSearchConsole) {
        }
        try {
            $cliente->propiedades();
        } catch (ErrorSearchConsole) {
        }

        // Dos peticiones al token: la segunda llamada no reutilizó el token que Google acababa de rechazar.
        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN);
        $this->assertCount(2, Http::recorded(fn (Request $r) => $r->url() === self::TOKEN));
    }

    public function test_429_se_reintenta_de_forma_acotada_y_luego_falla_con_mensaje_de_cuota(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota']], 429)]);

        try {
            $this->cliente()->consultar('sc-domain:e.com', '2026-09-01', '2026-09-30', ['query']);
            $this->fail('Debió lanzar ErrorSearchConsole');
        } catch (ErrorSearchConsole $e) {
            $this->assertStringContainsString('cuota', $e->getMessage());
            $this->assertSame(429, $e->estadoHttp);
        }

        // 1 token + exactamente 3 intentos de consulta.
        Http::assertSentCount(4);
    }

    public function test_un_429_pasajero_se_recupera_en_el_reintento(): void
    {
        Http::fake([
            self::TOKEN => $this->tokenOk(),
            'www.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Quota']], 429)
                ->push(['rows' => [['keys' => ['a'], 'clicks' => 1, 'impressions' => 2, 'ctr' => 0.5, 'position' => 2]]]),
        ]);

        $filas = $this->cliente()->consultar('sc-domain:e.com', '2026-09-01', '2026-09-30', ['query']);

        $this->assertCount(1, $filas);
    }

    public function test_un_5xx_persistente_da_error_temporal(): void
    {
        Http::fake([self::TOKEN => $this->tokenOk(), 'www.googleapis.com/*' => Http::response('boom', 503)]);

        $this->expectException(ErrorSearchConsole::class);
        $this->expectExceptionMessage('error temporal (503)');

        $this->cliente()->propiedades();
    }

    public function test_el_saneador_quita_llaves_rutas_y_acota_el_largo(): void
    {
        $sucio = "Fallo en C:\\laragon\\www\\rankpro\\app\\X.php y /var/www/html/app/Y.php -----BEGIN PRIVATE KEY-----\nMIIabc\n-----END PRIVATE KEY----- token=SECRETO-LARGO-123 ver https://www.googleapis.com/webmasters/v3/sites";
        $limpio = Saneador::mensaje($sucio, 300, ['SECRETO-LARGO-123']);

        $this->assertStringNotContainsString('laragon', $limpio);
        $this->assertStringNotContainsString('/var/www', $limpio);
        $this->assertStringNotContainsString('MIIabc', $limpio);
        $this->assertStringNotContainsString('SECRETO-LARGO-123', $limpio);
        $this->assertStringContainsString('https://www.googleapis.com/webmasters/v3/sites', $limpio);

        $this->assertLessThanOrEqual(300, mb_strlen(Saneador::mensaje(str_repeat('a ', 500))));
    }

    public function test_normalizador_quita_acentos_mayusculas_y_espacios(): void
    {
        $this->assertSame('hotel en aguascalientes cerca del centro', NormalizadorKeyword::normalizar("  Hotel   EN Aguascalientes\tCérca del  Centro "));
        $this->assertSame('cafe nino', NormalizadorKeyword::normalizar('Café Niño'));
    }
}
