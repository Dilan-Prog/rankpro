<?php

namespace Tests\Feature\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Mail\CorreoPlantillaMail;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Models\ReporteEnlace;
use App\Models\ReporteProgramacion;
use App\Models\User;
use App\Support\Correo\Variables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Enlace público de solo lectura a la versión para cliente: el token solo existe en
 * claro al crearlo, vence, se revoca, no indexa y no enseña nada interno. Más la
 * variable de correo `{{enlace_reporte}}` que resuelve a este enlace.
 */
class ReporteEnlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private int $folio = 0;

    private function reporte(array $extra = [], bool $conResumen = true): Reporte
    {
        $reporte = Reporte::create($extra + [
            'cliente_id' => Cliente::factory()->create(['empresa' => 'Hotel Fratelli'])->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => 'Reporte SEO — Hotel Fratelli — Octubre 2026',
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'estado' => EstadoReporte::Listo->value,
            'numero' => 'REP-SEO-2026-'.str_pad((string) ++$this->folio, 4, '0', STR_PAD_LEFT),
            'notas_alcance' => 'NOTA-INTERNA-DE-ALCANCE',
        ]);

        if ($conResumen) {
            $reporte->secciones()->create([
                'tipo' => 'resumen_cliente', 'titulo' => 'Resumen', 'orden' => 1, 'visible' => true,
                'contenido' => [
                    'logros' => [['texto' => 'Subió el tráfico orgánico.']],
                    'retos' => [['texto' => 'Bajaron los clics.']],
                    'proximos_pasos' => [['texto' => 'Revisar páginas de habitaciones.']],
                    'grafica' => ['titulo' => 'Tráfico', 'etiqueta_x' => 'Mes', 'series' => [['clave' => 't', 'titulo' => 'Tráfico']], 'filas' => [['x' => 'oct 2026', 'valores' => ['t' => 5]]]],
                ],
            ]);
            $reporte->secciones()->create([
                'tipo' => 'texto', 'titulo' => 'Pagos', 'orden' => 2, 'visible' => true, 'interno' => true,
                'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Tarjeta', 'cuerpo' => 'SECRETO-INTERNO']]],
            ]);
        }

        return $reporte;
    }

    /** @return array{0: string, 1: string} token y url relativa */
    private function crear(Reporte $reporte): array
    {
        $r = $this->actingAs(User::factory()->create())->postJson(route('admin.reportes.enlaces.store', $reporte))->assertCreated();
        $url = $r->json('url');
        $this->assertMatchesRegularExpression('~/r/[A-Za-z0-9]{48}$~', $url);

        return [substr($url, strrpos($url, '/') + 1), parse_url($url, PHP_URL_PATH)];
    }

    // --- crear / revocar (panel) -----------------------------------------

    public function test_el_token_se_guarda_hasheado_nunca_en_claro_y_vence_a_los_30_dias(): void
    {
        $reporte = $this->reporte();
        [$token] = $this->crear($reporte);

        $enlace = ReporteEnlace::firstOrFail();
        $this->assertSame(hash('sha256', $token), $enlace->token_hash);
        $this->assertDatabaseMissing('reporte_enlaces', ['token_hash' => $token]);
        $this->assertSame('2026-12-05', $enlace->expira_en->toDateString());
        $this->assertNull($enlace->revocado_en);
        $this->assertSame(0, $enlace->accesos);
        $this->assertSame($reporte->id, $enlace->reporte_id);

        // Ninguna fila del enlace ni su serialización lleva el token.
        $this->assertStringNotContainsString($token, json_encode($enlace->toRow()));
        $this->assertStringNotContainsString($token, json_encode($enlace->getAttributes()));
    }

    public function test_el_panel_no_vuelve_a_mostrar_el_token_solo_el_estado(): void
    {
        $reporte = $this->reporte();
        [$token] = $this->crear($reporte);

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reportes.show', $reporte))->assertOk()->getContent();

        $this->assertStringContainsString('Compartir con el cliente', $html);
        $this->assertStringContainsString('Vigente', $html);
        $this->assertStringNotContainsString($token, $html);
        $this->assertStringNotContainsString(hash('sha256', $token), $html);
    }

    public function test_crear_exige_sesion_y_un_resumen_visible(): void
    {
        $reporte = $this->reporte();
        $this->postJson(route('admin.reportes.enlaces.store', $reporte))->assertUnauthorized();

        $sinResumen = $this->reporte([], conResumen: false);
        $this->actingAs(User::factory()->create())->postJson(route('admin.reportes.enlaces.store', $sinResumen))->assertUnprocessable();
        $this->assertSame(0, ReporteEnlace::count());
    }

    public function test_revocar_solo_alcanza_a_los_enlaces_de_ese_reporte(): void
    {
        $user = User::factory()->create();
        $a = $this->reporte();
        $b = $this->reporte();
        $this->crear($b);
        $deB = ReporteEnlace::firstOrFail();

        // Con la URL del reporte A no se puede revocar el enlace del reporte B.
        $this->actingAs($user)->deleteJson(route('admin.reportes.enlaces.destroy', ['reporte' => $a, 'enlace' => $deB]))->assertNotFound();
        $this->assertNull($deB->fresh()->revocado_en);

        $this->actingAs($user)->deleteJson(route('admin.reportes.enlaces.destroy', ['reporte' => $b, 'enlace' => $deB]))->assertOk()->assertJsonPath('estado', 'revocado');
        $this->assertNotNull($deB->fresh()->revocado_en);
    }

    // --- vista pública ----------------------------------------------------

    public function test_la_vista_publica_funciona_sin_sesion_no_indexa_y_cuenta_accesos(): void
    {
        [$token, $ruta] = $this->crear($this->reporte());
        auth()->logout();

        $r = $this->get($ruta)->assertOk();

        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $r->headers->get('Referrer-Policy'));
        $html = html_entity_decode($r->getContent());
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive">', $html);
        $this->assertStringContainsString('Hotel Fratelli', $html);
        $this->assertStringContainsString('Subió el tráfico orgánico.', $html);
        $this->assertStringContainsString('Bajaron los clics.', $html);
        $this->assertStringContainsString('REP-SEO-2026-0001', $html);

        $enlace = ReporteEnlace::firstOrFail();
        $this->assertSame(1, $enlace->accesos);
        $this->assertNotNull($enlace->ultimo_acceso_en);

        $this->get($ruta)->assertOk();
        $this->assertSame(2, $enlace->fresh()->accesos);
    }

    public function test_la_vista_publica_no_enseña_ningun_dato_interno(): void
    {
        [, $ruta] = $this->crear($this->reporte());
        auth()->logout();

        $html = html_entity_decode($this->get($ruta)->assertOk()->getContent());

        foreach (['NOTA-INTERNA-DE-ALCANCE', 'SECRETO-INTERNO', 'Pagos', 'Tarjeta', 'notas_alcance', '/admin'] as $interno) {
            $this->assertStringNotContainsString($interno, $html);
        }
    }

    public function test_la_vista_publica_permite_descargar_el_pdf_de_una_pagina(): void
    {
        [, $ruta] = $this->crear($this->reporte());
        auth()->logout();

        $r = $this->get($ruta.'/pdf')->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('Content-Type'));
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('-cliente.pdf', (string) $r->headers->get('Content-Disposition'));
        $this->assertSame('%PDF', substr($r->getContent(), 0, 4));
        $this->assertSame(1, ReporteEnlace::firstOrFail()->accesos);
    }

    public function test_un_enlace_vencido_da_410_y_no_cuenta_acceso(): void
    {
        [, $ruta] = $this->crear($this->reporte());
        auth()->logout();

        Carbon::setTestNow(Carbon::create(2026, 12, 5, 10, 0, 1));

        $this->get($ruta)->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get($ruta.'/pdf')->assertStatus(410);
        $this->assertSame(0, ReporteEnlace::firstOrFail()->accesos);
        $this->assertSame('expirado', ReporteEnlace::firstOrFail()->estado());
    }

    public function test_un_enlace_revocado_da_410(): void
    {
        $reporte = $this->reporte();
        [, $ruta] = $this->crear($reporte);
        $this->deleteJson(route('admin.reportes.enlaces.destroy', ['reporte' => $reporte, 'enlace' => ReporteEnlace::firstOrFail()]))->assertOk();
        auth()->logout();

        $this->get($ruta)->assertStatus(410);
        $this->get($ruta.'/pdf')->assertStatus(410);
        $this->assertSame(0, ReporteEnlace::firstOrFail()->accesos);
    }

    public function test_un_token_desconocido_o_mal_formado_da_404_y_el_reporte_eliminado_tambien(): void
    {
        $reporte = $this->reporte();
        [, $ruta] = $this->crear($reporte);
        auth()->logout();

        $this->get('/r/'.str_repeat('a', 48))->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('/r/corto')->assertNotFound();
        $this->get('/r/'.str_repeat('a', 47).'-')->assertNotFound();

        $reporte->delete();
        $this->get($ruta)->assertNotFound();
    }

    public function test_si_el_resumen_se_oculta_el_enlace_deja_de_mostrar_el_reporte(): void
    {
        $reporte = $this->reporte();
        [, $ruta] = $this->crear($reporte);
        auth()->logout();

        $reporte->secciones()->where('tipo', 'resumen_cliente')->update(['visible' => false]);

        $this->get($ruta)->assertNotFound();
    }

    public function test_la_vista_publica_tiene_limite_de_peticiones_por_ip(): void
    {
        $codigos = [];
        for ($i = 0; $i < 31; $i++) {
            $codigos[] = $this->get('/r/'.str_repeat('b', 48))->getStatusCode();
        }

        $this->assertSame(404, $codigos[29]);
        $this->assertSame(429, $codigos[30]);
    }

    // --- {{enlace_reporte}} ----------------------------------------------

    public function test_la_variable_enlace_reporte_resuelve_al_enlace_cuando_hay_uno_vigente(): void
    {
        $reporte = $this->reporte();

        $this->assertSame('', Variables::enlaceReporte($reporte), 'sin enlace vigente queda vacía');

        $this->crear($reporte);
        $url = Variables::enlaceReporte($reporte);
        $this->assertMatchesRegularExpression('~/r/[A-Za-z0-9]{48}$~', $url);
        // El enlace del correo es válido de verdad.
        auth()->logout();
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
        $this->assertSame(2, ReporteEnlace::count());

        // Con todos revocados vuelve a quedar vacía.
        ReporteEnlace::query()->update(['revocado_en' => now()]);
        $this->assertSame('', Variables::enlaceReporte($reporte));
    }

    public function test_el_envio_programado_pone_el_enlace_en_la_variable_solo_si_el_reporte_tiene_uno_vigente(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['empresa' => 'Hotel Fratelli']);
        $reporte = $this->reporte(['cliente_id' => $cliente->id]);
        ReporteProgramacion::factory()->create([
            'cliente_id' => $cliente->id,
            'asunto' => 'Tu reporte: {{enlace_reporte}}',
            'proximo_envio_en' => now()->subMinute(),
        ]);

        // Sin enlace creado: la variable queda vacía, no se inventa uno.
        $this->artisan('reportes:enviar-programados')->assertSuccessful();
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => rtrim($m->asunto) === 'Tu reporte:');
        $this->assertSame(0, ReporteEnlace::count());

        // Con un enlace vigente, el correo lleva un enlace nuevo y válido.
        Mail::fake();
        $this->crear($reporte);
        ReporteProgramacion::query()->update(['proximo_envio_en' => now()->subMinute(), 'ultimo_ok' => null, 'ultimo_reporte_id' => null]);
        $reporte->update(['estado' => EstadoReporte::Listo->value]);

        $this->artisan('reportes:enviar-programados')->assertSuccessful();

        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => (bool) preg_match('~^Tu reporte: https?://[^ ]+/r/[A-Za-z0-9]{48}$~', $m->asunto));
        $this->assertSame(2, ReporteEnlace::count());
    }
}
