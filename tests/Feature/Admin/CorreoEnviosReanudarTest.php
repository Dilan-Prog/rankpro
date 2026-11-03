<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Mail\CorreoPlantillaMail;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\User;
use App\Services\Correo\EnviadorCorreo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Envíos interrumpidos a mitad (estado 'enviando'): reanudar, contar bien los
 * que ya salieron y no mandar dos veces ante una doble petición.
 */
class CorreoEnviosReanudarTest extends TestCase
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

    /** Envío en 'enviando' desde hace $minutos, con un destinatario ya enviado y uno pendiente. */
    private function envioInterrumpido(int $minutos): CorreoEnvio
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => CorreoPlantilla::factory()->create()->id, 'estado' => 'enviando']);
        CorreoEnvio::whereKey($envio->id)->update(['updated_at' => now()->subMinutes($minutos)]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'ya@ejemplo.com', 'estado' => 'enviado']);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'falta@ejemplo.com']);

        return $envio->fresh();
    }

    public function test_un_envio_atascado_se_reanuda_manda_solo_a_los_pendientes_y_queda_enviado(): void
    {
        $envio = $this->envioInterrumpido(30);

        $resultado = app(EnviadorCorreo::class)->enviar($envio);

        $this->assertSame(EstadoEnvioCorreo::Enviado, $resultado->estado);
        Mail::assertSent(CorreoPlantillaMail::class, 1);
        Mail::assertSent(CorreoPlantillaMail::class, fn ($m) => $m->hasTo('falta@ejemplo.com'));
    }

    public function test_reanudar_cuenta_los_enviados_acumulados_y_no_marca_fallido(): void
    {
        $envio = $this->envioInterrumpido(30);
        // El que faltaba rebota: en esta corrida salieron 0, pero antes ya salió uno.
        CorreoDestinatario::where('email', 'falta@ejemplo.com')->update(['email' => 'no-es-correo']);

        $resultado = app(EnviadorCorreo::class)->enviar($envio);

        $this->assertSame(EstadoEnvioCorreo::Enviado, $resultado->estado);
    }

    public function test_un_envio_en_enviando_reciente_no_se_puede_mandar_otra_vez(): void
    {
        $envio = $this->envioInterrumpido(1);

        try {
            app(EnviadorCorreo::class)->enviar($envio);
            $this->fail('Debió rechazarse: otra petición lo está enviando.');
        } catch (\RuntimeException $e) {
            Mail::assertNothingSent();
        }
    }

    public function test_doble_peticion_solo_la_primera_envia(): void
    {
        $envio = CorreoEnvio::factory()->create(['plantilla_id' => CorreoPlantilla::factory()->create()->id]);
        CorreoDestinatario::factory()->create(['envio_id' => $envio->id, 'email' => 'uno@ejemplo.com']);

        // Dos peticiones que cargaron el mismo envío mientras seguía en borrador.
        $copiaA = CorreoEnvio::find($envio->id);
        $copiaB = CorreoEnvio::find($envio->id);

        app(EnviadorCorreo::class)->enviar($copiaA);
        try {
            app(EnviadorCorreo::class)->enviar($copiaB);
            $this->fail('La segunda petición no debió enviar.');
        } catch (\RuntimeException $e) {
            // esperado
        }

        Mail::assertSent(CorreoPlantillaMail::class, 1);
    }

    public function test_el_endpoint_enviar_acepta_un_envio_atascado_y_rechaza_uno_reciente(): void
    {
        $usuario = User::factory()->create();

        $atascado = $this->envioInterrumpido(30);
        $this->actingAs($usuario)->postJson(route('admin.correo.envios.enviar', $atascado))->assertOk();
        $this->assertSame(EstadoEnvioCorreo::Enviado, $atascado->fresh()->estado);

        $reciente = $this->envioInterrumpido(1);
        $this->actingAs($usuario)->postJson(route('admin.correo.envios.enviar', $reciente))->assertStatus(422);
    }

    public function test_la_ficha_ofrece_reanudar_solo_si_esta_atascado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('admin.correo.envios.show', $this->envioInterrumpido(30)))
            ->assertOk()->assertSee('Reanudar envío');
        $this->actingAs($usuario)->get(route('admin.correo.envios.show', $this->envioInterrumpido(1)))
            ->assertOk()->assertDontSee('Reanudar envío');
    }

    public function test_el_comando_reanuda_los_atascados(): void
    {
        $atascado = $this->envioInterrumpido(30);
        $reciente = $this->envioInterrumpido(1);

        $this->artisan('correo:procesar-programados')->assertSuccessful();

        $this->assertSame(EstadoEnvioCorreo::Enviado, $atascado->fresh()->estado);
        $this->assertSame(EstadoEnvioCorreo::Enviando, $reciente->fresh()->estado);
        $this->assertSame(EstadoDestinatarioCorreo::Pendiente, $reciente->destinatarios()->where('email', 'falta@ejemplo.com')->first()->estado);
    }
}
