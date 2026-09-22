<?php

namespace Tests\Feature\Admin;

use App\Models\ConfiguracionSmtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Configuración de correo (SMTP): la fila única (id=1) que, activada,
 * sobreescribe los MAIL_* del .env — ver App\Support\ConfiguracionSmtpAplicador.
 */
class ConfiguracionSmtpTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'activa' => '1',
            'host' => 'smtp.hostinger.com',
            'puerto' => '465',
            'cifrado' => 'ssl',
            'usuario' => 'hola@rankprosolutions.com.mx',
            'password' => 'secreta-123',
            'remitente_email' => 'hola@rankprosolutions.com.mx',
            'remitente_nombre' => 'RankPro Solutions',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.configuracion.smtp.edit'))->assertRedirect(route('login'));
    }

    public function test_edit_renders_with_no_config_yet(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('admin.configuracion.smtp.edit'));

        $response->assertOk();
        $response->assertViewHas('tieneContrasena', false);
    }

    public function test_update_creates_the_singleton_row(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.configuracion.smtp.update'), $this->payload())
            ->assertRedirect(route('admin.configuracion.smtp.edit'));

        $config = ConfiguracionSmtp::actual();
        $this->assertNotNull($config);
        $this->assertSame(1, $config->id);
        $this->assertTrue($config->activa);
        $this->assertSame('smtp.hostinger.com', $config->host);
        $this->assertSame(465, $config->puerto);
        $this->assertSame('ssl', $config->cifrado);
        $this->assertSame('secreta-123', $config->password);
    }

    public function test_password_is_stored_encrypted_at_rest(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.configuracion.smtp.update'), $this->payload());

        $crudo = DB::table('configuracion_smtp')->where('id', 1)->value('password');

        $this->assertNotSame('secreta-123', $crudo);
        $this->assertSame('secreta-123', ConfiguracionSmtp::actual()->password);
    }

    public function test_saving_again_with_blank_password_keeps_the_previous_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload());

        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload([
            'password' => '',
            'host' => 'smtp.otro-proveedor.com',
        ]));

        $config = ConfiguracionSmtp::actual();
        $this->assertSame('smtp.otro-proveedor.com', $config->host);
        $this->assertSame('secreta-123', $config->password);
    }

    public function test_saving_with_a_new_password_replaces_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload());

        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload(['password' => 'otra-clave-456']));

        $this->assertSame('otra-clave-456', ConfiguracionSmtp::actual()->password);
    }

    public function test_edit_reports_tiene_contrasena_true_once_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload());

        $this->actingAs($user)->get(route('admin.configuracion.smtp.edit'))
            ->assertViewHas('tieneContrasena', true);
    }

    public function test_update_requires_host_and_puerto(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.configuracion.smtp.update'), $this->payload(['host' => '', 'puerto' => '']))
            ->assertSessionHasErrors(['host', 'puerto']);
    }

    public function test_probar_sends_a_test_email_with_the_submitted_values(): void
    {
        Mail::fake();

        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.configuracion.smtp.probar'), [
            'email' => 'dilan@example.com',
            'host' => 'smtp.hostinger.com',
            'puerto' => 465,
            'cifrado' => 'ssl',
            'usuario' => 'hola@rankprosolutions.com.mx',
            'password' => 'secreta-123',
            'remitente_email' => 'hola@rankprosolutions.com.mx',
            'remitente_nombre' => 'RankPro Solutions',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_probar_fails_with_422_when_there_is_no_password_anywhere(): void
    {
        Mail::fake();

        $response = $this->actingAs(User::factory()->create())->postJson(route('admin.configuracion.smtp.probar'), [
            'email' => 'dilan@example.com',
            'host' => 'smtp.hostinger.com',
            'puerto' => 465,
        ]);

        $response->assertStatus(422);
    }

    public function test_probar_falls_back_to_the_saved_password_when_the_field_is_left_blank(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.configuracion.smtp.update'), $this->payload());

        $response = $this->actingAs($user)->postJson(route('admin.configuracion.smtp.probar'), [
            'email' => 'dilan@example.com',
            'host' => 'smtp.hostinger.com',
            'puerto' => 465,
            'cifrado' => 'ssl',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }
}
