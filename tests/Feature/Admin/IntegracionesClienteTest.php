<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla /admin/clientes/{cliente}/integraciones: el token de tracking va
 * enmascarado por defecto y "Regenerar token" pasa por un modal propio.
 *
 * El token no es secreto (viaja en el HTML del sitio del cliente), pero no
 * tiene por qué verse en pantalla: el snippet completo solo puede aparecer en
 * data-completo (lo que copia el botón Copiar) y en el <noscript> de respaldo.
 */
class IntegracionesClienteTest extends TestCase
{
    use RefreshDatabase;

    private function clienteConToken(): Cliente
    {
        $cliente = Cliente::factory()->create();
        $cliente->forceFill(['api_token' => 'rp_live_'.str_repeat('Ab3', 18).'Z9yx'])->save();

        return $cliente;
    }

    /** HTML visible: sin los atributos data-completo y sin el <noscript> de respaldo. */
    private function htmlVisible(string $html): string
    {
        $html = preg_replace('/\sdata-completo="[^"]*"/', '', $html);

        return preg_replace('#<noscript>.*?</noscript>#s', '', $html);
    }

    public function test_el_token_completo_no_se_ve_en_texto_plano_por_defecto(): void
    {
        $cliente = $this->clienteConToken();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.clientes.integraciones', $cliente))
            ->assertOk()
            ->getContent();

        $visible = $this->htmlVisible($html);
        $this->assertStringNotContainsString($cliente->api_token, $visible);
        $this->assertStringNotContainsString('Ab3Ab3Ab3', $visible);
        // Enmascarado: prefijo + puntos + últimos 4, en el input y en la guía.
        $this->assertSame(2, substr_count($visible, 'rp_live_••••••••••Z9yx.js'));
    }

    public function test_el_snippet_completo_sigue_disponible_para_copiar(): void
    {
        $cliente = $this->clienteConToken();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.clientes.integraciones', $cliente))
            ->getContent();

        $esperado = e('<script src="'.route('tracking.snippet', ['token' => $cliente->api_token]).'"></script>');
        $this->assertSame(2, substr_count($html, 'data-completo="'.$esperado.'"'));
        $this->assertStringContainsString('data-token-copiar', $html);
        $this->assertStringContainsString('data-token-mostrar', $html);
        // Sin JS queda el snippet completo en <noscript>.
        $this->assertMatchesRegularExpression('#<noscript>.*'.preg_quote($cliente->api_token, '#').'.*</noscript>#s', $html);
    }

    public function test_regenerar_usa_un_modal_propio_y_no_el_confirm_nativo(): void
    {
        $cliente = $this->clienteConToken();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.clientes.integraciones', $cliente))
            ->getContent();

        $this->assertStringContainsString('id="regenerarTokenModal"', $html);
        $this->assertStringContainsString('data-abrir-modal="regenerarTokenModal"', $html);
        $this->assertStringContainsString('dejará de funcionar', $html);
        $this->assertStringContainsString('data-modal-close="regenerarTokenModal"', $html);
        $this->assertStringNotContainsString('data-confirm', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        // El formulario del modal es el mismo POST de siempre.
        $this->assertStringContainsString('action="'.route('admin.clientes.integraciones.token', $cliente).'"', $html);
    }

    public function test_sin_token_no_hay_modal_ni_snippet(): void
    {
        $cliente = Cliente::factory()->create();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.clientes.integraciones', $cliente))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('regenerarTokenModal', $html);
        $this->assertStringNotContainsString('data-token-bloque', $html);
        $this->assertStringContainsString('Generar Token', $html);
    }

    public function test_regenerar_cambia_el_token_y_el_anterior_deja_de_coincidir(): void
    {
        $cliente = $this->clienteConToken();
        $anterior = $cliente->api_token;

        $this->actingAs(User::factory()->create())
            ->post(route('admin.clientes.integraciones.token', $cliente))
            ->assertRedirect(route('admin.clientes.integraciones', $cliente));

        $nuevo = $cliente->fresh()->api_token;
        $this->assertNotSame($anterior, $nuevo);
        $this->assertStringStartsWith('rp_live_', $nuevo);
        $this->assertNull(Cliente::where('api_token', $anterior)->first());
        $this->assertNotNull($cliente->fresh()->api_token_regenerated_at);
    }
}
