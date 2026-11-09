<?php

namespace Tests\Feature\Portal;

use App\Models\PortalAcceso;
use App\Models\PortalVisita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Seguridad del enlace mágico: el token solo existe en claro al crearlo, 404/410 sin filtrar si
 * existió, cabeceras en TODAS las respuestas, throttle, sin cookies y auditoría sin IP ni token.
 */
class PortalSeguridadTest extends TestCase
{
    use PreparaPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPortal();
        RateLimiter::clear('portal|127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->cerrarPortal();
        parent::tearDown();
    }

    private function assertCabeceras($respuesta): void
    {
        $respuesta->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $respuesta->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    // --- token ---------------------------------------------------------------

    public function test_el_token_se_guarda_solo_hasheado_y_nunca_en_claro_ni_en_la_auditoria(): void
    {
        [$acceso, $token] = $this->enlace($this->cliente());

        $this->assertSame(hash('sha256', $token), $acceso->token_hash);
        $this->assertSame(48, strlen($token));
        $this->assertDatabaseMissing('portal_accesos', ['token_hash' => $token]);

        $this->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->get($this->ruta('portal.reportes', $token))->assertOk();

        // Ninguna columna de ninguna fila del portal contiene el token, y el modelo no serializa su hash.
        $volcado = json_encode(DB::table('portal_accesos')->get()).json_encode(DB::table('portal_visitas')->get());
        $this->assertStringNotContainsString($token, $volcado);
        $this->assertStringNotContainsString($token, json_encode($acceso->toRow()));
        $this->assertArrayNotHasKey('token_hash', $acceso->toArray());
    }

    public function test_el_token_no_aparece_en_los_logs(): void
    {
        [, $token] = $this->enlace($this->cliente());
        $log = storage_path('logs/laravel.log');
        $antes = is_file($log) ? (string) file_get_contents($log) : '';

        $this->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => 999999]))->assertNotFound();
        $this->get($this->ruta('portal.inicio', 'x'.substr($token, 1)))->assertNotFound();

        $despues = is_file($log) ? (string) file_get_contents($log) : '';
        $this->assertStringNotContainsString($token, substr($despues, strlen($antes)));
    }

    // --- 404 / 410 -------------------------------------------------------------

    public function test_un_token_inventado_o_mal_formado_da_404(): void
    {
        $this->enlace($this->cliente());

        $this->get('/portal/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/portal/corto')->assertNotFound();
        $this->get('/portal/'.str_repeat('a', 47).'-')->assertNotFound();
        $this->get('/portal/'.str_repeat('a', 48).'/reportes')->assertNotFound();
        $this->assertSame(0, PortalVisita::count());
    }

    public function test_vencido_y_revocado_dan_410_con_el_mismo_texto_sin_filtrar_si_existieron(): void
    {
        $cliente = $this->cliente();
        [$vencido, $tokenVencido] = $this->enlace($cliente);
        [$revocado, $tokenRevocado] = $this->enlace($cliente);
        $vencido->forceFill(['expira_en' => now()->subSecond()])->save();
        $revocado->forceFill(['revocado_en' => now()])->save();

        $a = $this->get($this->ruta('portal.inicio', $tokenVencido))->assertStatus(410);
        $b = $this->get($this->ruta('portal.reportes', $tokenRevocado))->assertStatus(410);

        $this->assertSame($a->getContent(), $b->getContent());
        $this->assertStringNotContainsString($cliente->nombre_comercial, $a->getContent());
        $this->assertSame(0, PortalVisita::count(), 'un enlace no vigente no registra visitas');
    }

    public function test_la_vigencia_elegida_se_respeta_y_vence_a_su_hora(): void
    {
        foreach ([7, 30, 90] as $dias) {
            [$acceso] = $this->enlace($this->cliente("Cliente {$dias}"), $dias);
            $this->assertSame(now()->addDays($dias)->toDateString(), $acceso->expira_en->toDateString());
        }

        [$acceso, $token] = $this->enlace($this->cliente('Otro'), 7);
        $this->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->travelTo(now()->addDays(7)->addSecond());
        $this->get($this->ruta('portal.inicio', $token))->assertStatus(410);
    }

    public function test_un_cliente_eliminado_deja_el_enlace_no_disponible(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $cliente->delete();

        $this->get($this->ruta('portal.inicio', $token))->assertStatus(410);
    }

    // --- cabeceras, cookies, throttle ---------------------------------------------

    public function test_todas_las_pantallas_y_los_errores_llevan_noindex_no_referrer_y_no_store(): void
    {
        $cliente = $this->cliente();
        [$acceso, $token] = $this->enlace($cliente);
        $reporte = $this->reporte($cliente);
        $propuesta = $this->propuesta($cliente);
        $entrega = $reporte->entregas()->where('formato', 'pdf_cliente')->firstOrFail();

        $rutas = [
            $this->ruta('portal.inicio', $token),
            $this->ruta('portal.reportes', $token),
            $this->ruta('portal.propuestas', $token),
            $this->ruta('portal.metricas', $token),
            $this->ruta('portal.reportes.descargar', $token, ['entrega' => $entrega->id]),
            $this->ruta('portal.propuestas.descargar', $token, ['propuesta' => $propuesta->id]),
        ];
        foreach ($rutas as $url) {
            $this->assertCabeceras($this->get($url)->assertOk());
        }

        // Errores: desconocida dentro del portal, descarga inexistente, token inventado, vencido y método no admitido.
        $this->assertCabeceras($this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => 987654]))->assertNotFound());
        $this->assertCabeceras($this->get($this->ruta('portal.inicio', $token).'/nada-aqui')->assertNotFound());
        $this->assertCabeceras($this->post($this->ruta('portal.inicio', $token))->assertNotFound());
        $this->assertCabeceras($this->get('/portal/'.str_repeat('z', 48))->assertNotFound());
        $acceso->forceFill(['expira_en' => now()->subDay()])->save();
        $this->assertCabeceras($this->get($this->ruta('portal.inicio', $token))->assertStatus(410));
    }

    public function test_las_paginas_llevan_meta_robots_y_no_dejan_cookies(): void
    {
        [, $token] = $this->enlace($this->cliente());

        $respuesta = $this->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive">', $respuesta->getContent());
        $this->assertSame([], $respuesta->headers->getCookies(), 'el portal no debe dejar cookies');
    }

    public function test_el_throttle_corta_en_429_con_las_mismas_cabeceras(): void
    {
        $this->enlace($this->cliente());
        $url = '/portal/'.str_repeat('q', 48);

        for ($i = 0; $i < 60; $i++) {
            $this->get($url)->assertNotFound();
        }

        $limitado = $this->get($url)->assertStatus(429);
        $this->assertCabeceras($limitado);
    }

    // --- auditoría ----------------------------------------------------------------

    public function test_cada_visita_crea_una_fila_sin_ip_en_claro_ni_token_y_cuenta_el_acceso(): void
    {
        [$acceso, $token] = $this->enlace($this->cliente());

        $this->withHeader('User-Agent', str_repeat('Navegador/1.0 ', 40))->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->get($this->ruta('portal.reportes', $token))->assertOk();

        $visitas = PortalVisita::orderBy('id')->get();
        $this->assertCount(2, $visitas);
        $this->assertSame(['/', '/reportes'], $visitas->pluck('ruta')->all());
        $this->assertSame(255, mb_strlen($visitas[0]->agente));

        foreach ($visitas as $v) {
            $this->assertSame($acceso->id, $v->portal_acceso_id);
            $this->assertNotSame('127.0.0.1', $v->ip_hash);
            $this->assertSame(64, strlen($v->ip_hash));
            $this->assertSame(hash_hmac('sha256', '127.0.0.1', config('app.key')), $v->ip_hash);
            $this->assertStringNotContainsString($token, $v->ruta);
        }
        $filas = json_encode(DB::table('portal_visitas')->get());
        $this->assertStringNotContainsString('127.0.0.1', $filas);

        $acceso->refresh();
        $this->assertSame(2, $acceso->accesos);
        $this->assertNotNull($acceso->ultimo_acceso_en);
    }

    public function test_los_enlaces_viven_en_su_tabla_y_borrar_el_acceso_borra_sus_visitas(): void
    {
        [$acceso, $token] = $this->enlace($this->cliente());
        $this->get($this->ruta('portal.inicio', $token))->assertOk();
        $this->assertSame(1, PortalVisita::count());

        $acceso->delete();

        $this->assertSame(0, PortalVisita::count());
        $this->assertSame(0, PortalAcceso::count());
    }
}
