<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Mail\CorreoPlantillaMail;
use App\Models\Cliente;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\User;
use App\Services\Correo\EnviadorCorreo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Tests de envíos de correo: CorreoEnviosController (redactar/enviar/programar/
 * cancelar/prueba), EnviadorCorreo, CorreoTrackingController (píxel y clics)
 * y el comando correo:procesar-programados.
 *
 * Todo con Mail::fake(): se comprueba qué Mailable salió y con qué HTML, no
 * el SMTP. Carbon::setTestNow() fija el reloj para lo programado.
 */
class CorreoEnviosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-09-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- helpers -------------------------------------------------------------

    /** Plantilla con un párrafo que usa {{contacto}} y un botón con enlace externo. */
    private function plantilla(array $overrides = []): CorreoPlantilla
    {
        return CorreoPlantilla::factory()->create(array_merge([
            'asunto' => 'Reporte de {{mes}}',
            'bloques' => [
                ['tipo' => 'heading', 'texto' => 'Resultados de {{mes}}'],
                ['tipo' => 'text', 'texto' => 'Hola {{contacto}}, aquí va el reporte de {{cliente}}.'],
                ['tipo' => 'button', 'texto' => 'Ver reporte', 'url' => 'https://ejemplo.com/reporte'],
            ],
        ], $overrides));
    }

    private function payloadStore(CorreoPlantilla $plantilla, array $overrides = []): array
    {
        return array_merge([
            'plantilla_id' => $plantilla->id,
            'asunto' => 'Reporte de {{mes}}',
            'remitente_nombre' => 'RankPro',
            'remitente_email' => 'administracion@rankprosolutions.com.mx',
            'variables' => ['mes' => 'agosto 2026'],
            'destinatarios' => [['email' => 'alguien@ejemplo.com', 'nombre' => 'Alguien']],
            'accion' => 'borrador',
        ], $overrides);
    }

    /** Envío con N destinatarios pendientes, listo para EnviadorCorreo. */
    private function envioConDestinatarios(int $cuantos = 1, array $overrides = []): CorreoEnvio
    {
        $envio = CorreoEnvio::factory()->create(array_merge(['plantilla_id' => $this->plantilla()->id], $overrides));
        CorreoDestinatario::factory()->count($cuantos)->create(['envio_id' => $envio->id]);

        return $envio;
    }

    // --- auth ----------------------------------------------------------------

    public function test_admin_pages_redirect_guests_to_login(): void
    {
        $envio = $this->envioConDestinatarios();

        $this->get(route('admin.correo.envios.index'))->assertRedirect(route('login'));
        $this->get(route('admin.correo.envios.create'))->assertRedirect(route('login'));
        $this->get(route('admin.correo.envios.show', $envio))->assertRedirect(route('login'));
        $this->get(route('admin.correo.envios.edit', $envio))->assertRedirect(route('login'));
    }

    public function test_json_endpoints_require_authentication(): void
    {
        $envio = $this->envioConDestinatarios();

        $this->postJson(route('admin.correo.envios.store'), [])->assertStatus(401);
        $this->postJson(route('admin.correo.envios.prueba'), [])->assertStatus(401);
        $this->putJson(route('admin.correo.envios.update', $envio), [])->assertStatus(401);
        $this->deleteJson(route('admin.correo.envios.destroy', $envio))->assertStatus(401);
        $this->postJson(route('admin.correo.envios.enviar', $envio))->assertStatus(401);
        $this->postJson(route('admin.correo.envios.programar', $envio), [])->assertStatus(401);
        $this->postJson(route('admin.correo.envios.cancelar', $envio))->assertStatus(401);

        Mail::assertNothingSent();
    }

    // --- store: enviar ---------------------------------------------------------

    public function test_store_with_accion_enviar_sends_one_mail_per_destinatario(): void
    {
        $plantilla = $this->plantilla();
        $clienteA = Cliente::factory()->create(['empresa' => 'Hotel Fratelli', 'contacto_nombre' => 'María López', 'email' => 'maria@fratelli.test']);
        $clienteB = Cliente::factory()->create(['empresa' => 'Equiterm', 'contacto_nombre' => 'Jorge Ruiz', 'email' => 'jorge@equiterm.test']);

        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($plantilla, [
                'accion' => 'enviar',
                'destinatarios' => [
                    ['cliente_id' => $clienteA->id, 'email' => $clienteA->email, 'nombre' => $clienteA->contacto_nombre],
                    ['cliente_id' => $clienteB->id, 'email' => $clienteB->email, 'nombre' => $clienteB->contacto_nombre],
                    ['email' => 'suelto@ejemplo.com', 'nombre' => 'Ana', 'variables' => ['contacto' => 'Ana', 'cliente' => 'Suelta SA']],
                ],
            ])
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('row.estado', 'enviado');
        $response->assertJsonPath('row.destinatarios', 3);
        $response->assertJsonPath('row.enviados', 3);
        $response->assertJsonPath('row.fallidos', 0);

        $envio = CorreoEnvio::firstOrFail();
        $response->assertJsonPath('show_url', route('admin.correo.envios.show', $envio));

        $this->assertSame(EstadoEnvioCorreo::Enviado, $envio->estado);
        $this->assertNotNull($envio->enviado_en);
        $this->assertNotEmpty($envio->html_congelado);
        // El congelado es el genérico: con variables del envío, sin píxel ni token.
        $this->assertStringContainsString('Resultados de agosto 2026', $envio->html_congelado);
        $this->assertStringNotContainsString('/correo/a/', $envio->html_congelado);
        $this->assertStringNotContainsString('/correo/c/', $envio->html_congelado);

        $destinatarios = $envio->destinatarios;
        $this->assertCount(3, $destinatarios);
        foreach ($destinatarios as $d) {
            $this->assertSame(EstadoDestinatarioCorreo::Enviado, $d->estado);
            $this->assertNotNull($d->enviado_en);
            $this->assertSame(48, strlen($d->token));
        }

        Mail::assertSent(CorreoPlantillaMail::class, 3);

        $tokenA = $destinatarios->firstWhere('email', $clienteA->email)->token;
        Mail::assertSent(CorreoPlantillaMail::class, function (CorreoPlantillaMail $mail) use ($clienteA, $tokenA) {
            return $mail->hasTo($clienteA->email)
                && $mail->asunto === 'Reporte de agosto 2026'
                && str_contains($mail->html, 'Hola María López, aquí va el reporte de Hotel Fratelli.')
                && str_contains($mail->html, 'correo/a/'.$tokenA)
                && str_contains($mail->html, '/correo/c/'.$tokenA.'?')
                && ! str_contains($mail->html, 'href="https://ejemplo.com/reporte"');
        });

        $tokenB = $destinatarios->firstWhere('email', $clienteB->email)->token;
        Mail::assertSent(CorreoPlantillaMail::class, function (CorreoPlantillaMail $mail) use ($clienteB, $tokenB) {
            return $mail->hasTo($clienteB->email)
                && str_contains($mail->html, 'Hola Jorge Ruiz, aquí va el reporte de Equiterm.')
                && str_contains($mail->html, 'correo/a/'.$tokenB);
        });

        // El correo suelto toma las variables propias del destinatario.
        $tokenSuelto = $destinatarios->firstWhere('email', 'suelto@ejemplo.com')->token;
        Mail::assertSent(CorreoPlantillaMail::class, function (CorreoPlantillaMail $mail) use ($tokenSuelto) {
            return $mail->hasTo('suelto@ejemplo.com')
                && str_contains($mail->html, 'Hola Ana, aquí va el reporte de Suelta SA.')
                && str_contains($mail->html, 'correo/a/'.$tokenSuelto);
        });
    }

    public function test_store_enviar_requires_at_least_one_destinatario(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['accion' => 'enviar', 'destinatarios' => []])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('destinatarios');
        Mail::assertNothingSent();
    }

    public function test_store_rejects_invalid_destinatario_email(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['destinatarios' => [['email' => 'no-es-correo']]])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('destinatarios.0.email');
    }

    public function test_store_rejects_unknown_variable_keys(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['variables' => ['inventada' => 'x']])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variables');
    }

    public function test_store_collapses_duplicate_emails(): void
    {
        $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['destinatarios' => [
                ['email' => 'Dup@Ejemplo.com'],
                ['email' => 'dup@ejemplo.com', 'nombre' => 'Con nombre'],
            ]])
        )->assertOk()->assertJsonPath('row.destinatarios', 1);

        $d = CorreoDestinatario::firstOrFail();
        $this->assertSame('dup@ejemplo.com', $d->email);
        $this->assertSame('Con nombre', $d->nombre);
    }

    public function test_store_borrador_saves_without_sending(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla())
        );

        $response->assertOk()->assertJsonPath('row.estado', 'borrador');
        Mail::assertNothingSent();
        $this->assertNull(CorreoEnvio::firstOrFail()->html_congelado);
    }

    public function test_store_programar_sets_estado_and_date(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['accion' => 'programar', 'programado_para' => '2026-09-18T15:30'])
        );

        $response->assertOk()->assertJsonPath('row.estado', 'programado')->assertJsonPath('row.programado_para', '2026-09-18 15:30');
        Mail::assertNothingSent();
    }

    public function test_store_programar_rejects_a_date_in_the_past(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(
            route('admin.correo.envios.store'),
            $this->payloadStore($this->plantilla(), ['accion' => 'programar', 'programado_para' => '2026-09-18 11:00'])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('programado_para');
    }

    // --- EnviadorCorreo directo -----------------------------------------------

    public function test_enviador_marks_invalid_email_as_fallido_and_sends_the_rest(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id]);
        $malo = CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'no-es-correo']);
        $bueno = CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'bueno@ejemplo.com']);

        $resultado = app(EnviadorCorreo::class)->enviar($envio);

        $this->assertSame(EstadoEnvioCorreo::Enviado, $resultado->estado);
        $this->assertSame(EstadoDestinatarioCorreo::Fallido, $malo->refresh()->estado);
        $this->assertSame('Dirección de correo no válida.', $malo->error);
        $this->assertNull($malo->enviado_en);
        $this->assertSame(EstadoDestinatarioCorreo::Enviado, $bueno->refresh()->estado);

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn (CorreoPlantillaMail $m) => $m->hasTo('bueno@ejemplo.com'));
    }

    public function test_enviador_marks_envio_fallido_when_nobody_receives_it(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'no-es-correo']);

        $resultado = app(EnviadorCorreo::class)->enviar($envio);

        $this->assertSame(EstadoEnvioCorreo::Fallido, $resultado->estado);
        $this->assertNull($resultado->enviado_en);
        Mail::assertNothingSent();
    }

    public function test_enviador_refuses_an_already_sent_envio(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'enviado']);

        $this->expectException(\RuntimeException::class);

        app(EnviadorCorreo::class)->enviar($envio);
    }

    public function test_enviador_skips_destinatarios_already_sent(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'ya@ejemplo.com', 'estado' => 'enviado']);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'nuevo@ejemplo.com']);

        app(EnviadorCorreo::class)->enviar($envio);

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn (CorreoPlantillaMail $m) => $m->hasTo('nuevo@ejemplo.com'));
    }

    // --- html_congelado ------------------------------------------------------

    public function test_editing_the_plantilla_after_sending_does_not_change_html_congelado(): void
    {
        $plantilla = $this->plantilla();
        $envio = $this->envioConDestinatarios(1, ['plantilla_id' => $plantilla->id]);

        $envio = app(EnviadorCorreo::class)->enviar($envio);
        $congelado = $envio->html_congelado;
        $this->assertStringContainsString('Resultados de agosto 2026', $congelado);

        $this->actingAs(User::factory()->create())->putJson(route('admin.correo.plantillas.update', $plantilla), [
            'nombre' => $plantilla->nombre,
            'categoria' => 'reportes',
            'estado' => 'activa',
            'asunto' => 'Otro asunto',
            'bloques' => [['tipo' => 'heading', 'texto' => 'TEXTO NUEVO']],
            'marca' => null,
            'html_personalizado' => null,
        ])->assertOk();

        $this->assertSame('TEXTO NUEVO', $plantilla->refresh()->bloques[0]['texto']);
        $this->assertSame($congelado, $envio->refresh()->html_congelado);

        // Y el detalle enseña lo congelado, no la plantilla editada.
        $response = $this->actingAs(User::factory()->create())->get(route('admin.correo.envios.show', $envio));
        $response->assertOk();
        $this->assertSame($congelado, $response->viewData('html'));
    }

    public function test_show_renders_a_preview_from_the_plantilla_before_sending(): void
    {
        $envio = $this->envioConDestinatarios();

        $response = $this->actingAs(User::factory()->create())->get(route('admin.correo.envios.show', $envio));

        $response->assertOk();
        $this->assertStringContainsString('Resultados de agosto 2026', $response->viewData('html'));
    }

    // --- píxel de apertura ---------------------------------------------------

    public function test_pixel_counts_openings_and_fixes_primera_apertura(): void
    {
        $destinatario = CorreoDestinatario::factory()->create(['envio_id' => $this->envioConDestinatarios(0)->id]);

        $response = $this->get(route('correo.abierto', ['token' => $destinatario->token]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $this->assertStringStartsWith('GIF89a', $response->getContent());

        $destinatario->refresh();
        $this->assertSame(1, $destinatario->aperturas);
        $this->assertNotNull($destinatario->primera_apertura_en);
        $this->assertTrue($destinatario->primera_apertura_en->eq(Carbon::parse('2026-09-18 12:00:00')));
        $this->assertSame(1, $destinatario->eventos()->where('tipo', 'apertura')->count());

        Carbon::setTestNow('2026-09-18 14:00:00');

        $this->get(route('correo.abierto', ['token' => $destinatario->token]))->assertOk();

        $destinatario->refresh();
        $this->assertSame(2, $destinatario->aperturas);
        $this->assertTrue($destinatario->primera_apertura_en->eq(Carbon::parse('2026-09-18 12:00:00')));
        $this->assertSame(2, $destinatario->eventos()->where('tipo', 'apertura')->count());
    }

    public function test_pixel_with_unknown_token_still_returns_the_gif(): void
    {
        $response = $this->get(route('correo.abierto', ['token' => 'no-existe']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        $this->assertStringStartsWith('GIF89a', $response->getContent());
        $this->assertDatabaseCount('correo_eventos', 0);
    }

    // --- clics ---------------------------------------------------------------

    public function test_signed_clic_redirects_and_counts(): void
    {
        $destinatario = CorreoDestinatario::factory()->create(['envio_id' => $this->envioConDestinatarios(0)->id]);
        $url = URL::signedRoute('correo.clic', ['token' => $destinatario->token, 'u' => 'https://ejemplo.com/x?a=1&b=2']);

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect('https://ejemplo.com/x?a=1&b=2');

        $destinatario->refresh();
        $this->assertSame(1, $destinatario->clics);
        $evento = $destinatario->eventos()->where('tipo', 'clic')->firstOrFail();
        $this->assertSame('https://ejemplo.com/x?a=1&b=2', $evento->url);
    }

    public function test_clic_without_signature_is_forbidden(): void
    {
        $destinatario = CorreoDestinatario::factory()->create(['envio_id' => $this->envioConDestinatarios(0)->id]);

        $response = $this->get(route('correo.clic', ['token' => $destinatario->token, 'u' => 'https://ejemplo.com/x']));

        $response->assertStatus(403);
        $this->assertSame(0, $destinatario->refresh()->clics);
    }

    public function test_clic_with_tampered_url_is_forbidden(): void
    {
        $destinatario = CorreoDestinatario::factory()->create(['envio_id' => $this->envioConDestinatarios(0)->id]);
        $url = URL::signedRoute('correo.clic', ['token' => $destinatario->token, 'u' => 'https://ejemplo.com/x']);

        $this->get(str_replace('ejemplo.com', 'malo.com', $url))->assertStatus(403);
        $this->assertSame(0, $destinatario->refresh()->clics);
    }

    public function test_signed_clic_with_unknown_token_still_redirects(): void
    {
        $url = URL::signedRoute('correo.clic', ['token' => 'no-existe', 'u' => 'https://ejemplo.com/x']);

        $this->get($url)->assertRedirect('https://ejemplo.com/x');
        $this->assertDatabaseCount('correo_eventos', 0);
    }

    public function test_signed_clic_with_non_http_url_returns_400(): void
    {
        $destinatario = CorreoDestinatario::factory()->create(['envio_id' => $this->envioConDestinatarios(0)->id]);
        $url = URL::signedRoute('correo.clic', ['token' => $destinatario->token, 'u' => 'javascript:alert(1)']);

        $this->get($url)->assertStatus(400);
        $this->assertSame(0, $destinatario->refresh()->clics);
    }

    // --- comando programados ---------------------------------------------------

    public function test_command_sends_due_envios_and_leaves_future_and_cancelled_alone(): void
    {
        $vencido = $this->envioConDestinatarios(1, ['estado' => 'programado', 'programado_para' => '2026-09-18 11:00:00']);
        $futuro = $this->envioConDestinatarios(1, ['estado' => 'programado', 'programado_para' => '2026-09-18 13:00:00']);
        $cancelado = $this->envioConDestinatarios(1, ['estado' => 'cancelado', 'programado_para' => '2026-09-18 10:00:00']);
        $borrador = $this->envioConDestinatarios(1, ['estado' => 'borrador']);

        $this->artisan('correo:procesar-programados')
            ->expectsOutput('Procesados: 1')
            ->assertSuccessful();

        $this->assertSame(EstadoEnvioCorreo::Enviado, $vencido->refresh()->estado);
        $this->assertNotNull($vencido->enviado_en);
        $this->assertSame(EstadoEnvioCorreo::Programado, $futuro->refresh()->estado);
        $this->assertSame(EstadoEnvioCorreo::Cancelado, $cancelado->refresh()->estado);
        $this->assertSame(EstadoEnvioCorreo::Borrador, $borrador->refresh()->estado);

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        $this->assertSame(EstadoDestinatarioCorreo::Pendiente, $futuro->destinatarios()->firstOrFail()->estado);
    }

    public function test_command_sends_a_future_envio_once_its_time_comes(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'programado', 'programado_para' => '2026-09-18 13:00:00']);

        $this->artisan('correo:procesar-programados')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow('2026-09-18 13:00:00');

        $this->artisan('correo:procesar-programados')->assertSuccessful();
        Mail::assertSent(CorreoPlantillaMail::class, 1);
        $this->assertSame(EstadoEnvioCorreo::Enviado, $envio->refresh()->estado);
    }

    // --- prueba --------------------------------------------------------------

    public function test_prueba_sends_one_mail_to_the_logged_user_without_creating_records(): void
    {
        $plantilla = $this->plantilla();
        $user = User::factory()->create(['email' => 'yo@rankpro.test']);

        $response = $this->actingAs($user)->postJson(route('admin.correo.envios.prueba'), [
            'plantilla_id' => $plantilla->id,
            'asunto' => 'Reporte de {{mes}}',
            'variables' => ['mes' => 'septiembre 2026'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('mensaje', 'Prueba enviada a yo@rankpro.test.');

        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, function (CorreoPlantillaMail $mail) {
            return $mail->hasTo('yo@rankpro.test')
                && $mail->asunto === '[Prueba] Reporte de septiembre 2026'
                // Lo que falta se rellena con los ejemplos del catálogo.
                && str_contains($mail->html, 'Hola María, aquí va el reporte de Hotel Fratelli.')
                && str_contains($mail->html, 'Resultados de septiembre 2026')
                // Sin píxel ni enlaces firmados.
                && ! str_contains($mail->html, '/correo/a/')
                && str_contains($mail->html, 'href="https://ejemplo.com/reporte"');
        });

        $this->assertDatabaseCount('correo_envios', 0);
        $this->assertDatabaseCount('correo_destinatarios', 0);
    }

    public function test_prueba_rejects_unknown_variable_keys(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.prueba'), [
            'plantilla_id' => $this->plantilla()->id,
            'asunto' => 'X',
            'variables' => ['inventada' => 'x'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variables');
        Mail::assertNothingSent();
    }

    // --- enviar / programar / cancelar sobre un envío existente ------------------

    public function test_enviar_endpoint_sends_a_borrador(): void
    {
        $envio = $this->envioConDestinatarios(2);

        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.enviar', $envio));

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('row.estado', 'enviado')->assertJsonPath('row.enviados', 2);
        Mail::assertSent(CorreoPlantillaMail::class, 2);
    }

    public function test_enviar_endpoint_refuses_an_envio_without_destinatarios(): void
    {
        $envio = $this->envioConDestinatarios(0);

        $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.enviar', $envio))->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_enviar_endpoint_refuses_an_already_sent_envio(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'enviado']);

        $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.enviar', $envio))->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_programar_endpoint_schedules_a_borrador(): void
    {
        $envio = $this->envioConDestinatarios();

        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.programar', $envio), [
            'programado_para' => '2026-09-19T09:00',
        ]);

        $response->assertOk()->assertJsonPath('row.estado', 'programado')->assertJsonPath('row.programado_para', '2026-09-19 09:00');
        $this->assertSame(EstadoEnvioCorreo::Programado, $envio->refresh()->estado);
    }

    public function test_cancelar_a_programado_sets_cancelado(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'programado', 'programado_para' => '2026-09-19 09:00:00']);

        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.cancelar', $envio));

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('row.estado', 'cancelado');
        $this->assertSame(EstadoEnvioCorreo::Cancelado, $envio->refresh()->estado);

        // Ya cancelado, el scheduler no lo toca.
        Carbon::setTestNow('2026-09-20 09:00:00');
        $this->artisan('correo:procesar-programados')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_cancelar_refuses_a_non_programado_envio(): void
    {
        $envio = $this->envioConDestinatarios();

        $this->actingAs(User::factory()->create())->postJson(route('admin.correo.envios.cancelar', $envio))->assertStatus(422);
        $this->assertSame(EstadoEnvioCorreo::Borrador, $envio->refresh()->estado);
    }

    // --- update / destroy ------------------------------------------------------

    public function test_update_of_a_programado_returns_it_to_borrador_and_syncs_destinatarios(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'programado', 'programado_para' => '2026-09-19 09:00:00']);
        $existente = $envio->destinatarios()->firstOrFail();

        $response = $this->actingAs(User::factory()->create())->putJson(
            route('admin.correo.envios.update', $envio),
            $this->payloadStore($envio->plantilla, ['destinatarios' => [
                ['email' => $existente->email, 'nombre' => 'Renombrado'],
                ['email' => 'nuevo@ejemplo.com'],
            ]])
        );

        $response->assertOk()->assertJsonPath('row.estado', 'borrador')->assertJsonPath('row.destinatarios', 2);

        $envio->refresh();
        $this->assertNull($envio->programado_para);
        // La fila que ya existía conserva su token (puede estar impreso en un píxel).
        $this->assertSame($existente->token, $envio->destinatarios()->where('email', $existente->email)->firstOrFail()->token);
        $this->assertSame('Renombrado', $envio->destinatarios()->where('email', $existente->email)->firstOrFail()->nombre);
    }

    public function test_update_refuses_a_sent_envio(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'enviado']);

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.correo.envios.update', $envio), $this->payloadStore($envio->plantilla))
            ->assertStatus(422);
    }

    public function test_destroy_deletes_a_borrador_but_not_a_sent_envio(): void
    {
        $borrador = $this->envioConDestinatarios();
        $enviado = $this->envioConDestinatarios(1, ['estado' => 'enviado']);
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson(route('admin.correo.envios.destroy', $borrador))
            ->assertOk()->assertExactJson(['ok' => true, 'deleted' => true]);
        $this->assertDatabaseMissing('correo_envios', ['id' => $borrador->id]);
        $this->assertDatabaseMissing('correo_destinatarios', ['envio_id' => $borrador->id]);

        $this->actingAs($user)->deleteJson(route('admin.correo.envios.destroy', $enviado))->assertStatus(422);
        $this->assertDatabaseHas('correo_envios', ['id' => $enviado->id]);
    }

    public function test_edit_of_a_sent_envio_redirects_to_show(): void
    {
        $envio = $this->envioConDestinatarios(1, ['estado' => 'enviado']);

        $this->actingAs(User::factory()->create())->get(route('admin.correo.envios.edit', $envio))
            ->assertRedirect(route('admin.correo.envios.show', $envio));
    }

    // --- KPIs de apertura -----------------------------------------------------

    public function test_to_row_calcula_apertura_sobre_enviados_e_ignora_fallidos(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id, 'estado' => 'enviado']);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'estado' => 'enviado', 'aperturas' => 1]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'estado' => 'enviado', 'aperturas' => 0]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'estado' => 'fallido']);

        $row = $envio->fresh()->load('destinatarios')->toRow();

        // El fallido no entra en el denominador: nunca pudo abrirse.
        $this->assertSame(2, $row['enviados']);
        $this->assertSame(50, $row['apertura']);
        $this->assertSame(1, $row['no_abiertos']);
    }

    public function test_kpis_del_index_respetan_el_periodo(): void
    {
        $user = User::factory()->create();
        $this->envioConDestinatarios(1, ['estado' => 'enviado', 'enviado_en' => now()->subDays(40)]);
        $this->envioConDestinatarios(1, ['estado' => 'enviado', 'enviado_en' => now()]);

        $this->assertSame(1, $this->actingAs($user)->get(route('admin.correo.envios.index', ['periodo' => 'mes']))
            ->assertOk()->viewData('kpis')['enviados']);
        $this->assertSame(1, $this->actingAs($user)->get(route('admin.correo.envios.index', ['periodo' => '30d']))
            ->assertOk()->viewData('kpis')['enviados']);
        $this->assertSame(2, $this->actingAs($user)->get(route('admin.correo.envios.index', ['periodo' => 'todo']))
            ->assertOk()->viewData('kpis')['enviados']);
    }

    public function test_periodo_invalido_cae_a_mes(): void
    {
        $this->envioConDestinatarios(1, ['estado' => 'enviado', 'enviado_en' => now()->subDays(40)]);

        $kpis = $this->actingAs(User::factory()->create())
            ->get(route('admin.correo.envios.index', ['periodo' => 'siempre']))
            ->assertOk()->viewData('kpis');

        $this->assertSame('mes', $kpis['periodo']);
        $this->assertSame(0, $kpis['enviados']);
    }

    public function test_kpis_exponen_no_abiertos(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => $this->plantilla()->id, 'estado' => 'enviado', 'enviado_en' => now()]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'estado' => 'enviado', 'aperturas' => 2]);
        CorreoDestinatario::factory()->count(2)->create(['envio_id' => $envio->id, 'estado' => 'enviado', 'aperturas' => 0]);

        $kpis = $this->actingAs(User::factory()->create())
            ->get(route('admin.correo.envios.index'))
            ->assertOk()->viewData('kpis');

        $this->assertSame(3, $kpis['alcanzados']);
        $this->assertSame(1, $kpis['abiertos']);
        $this->assertSame(2, $kpis['no_abiertos']);
        $this->assertSame(33, $kpis['apertura']);
    }
}
