<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CRUD de la pantalla de administración de webhooks + botón "Probar" +
 * reintento manual de entregas. El envío real (Despachador) ya tiene su
 * propia responsabilidad; aquí solo se verifica que el controlador la
 * conecte bien con la UI.
 */
class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_lista_webhooks(): void
    {
        $this->actor();
        Webhook::factory()->create(['nombre' => 'n8n prod']);

        $this->get('/admin/integraciones/webhooks')
            ->assertOk()
            ->assertSee('n8n prod');
    }

    public function test_crea_webhook_y_muestra_secreto_una_vez(): void
    {
        $this->actor();

        $respuesta = $this->postJson('/admin/integraciones/webhooks', [
            'nombre' => 'n8n - clientes',
            'url' => 'https://n8n.midominio.com/webhook/rankpro',
            'eventos' => ['cliente.creado', 'cliente.actualizado'],
            'activo' => true,
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['row', 'secreto']);

        $this->assertDatabaseHas('webhooks', ['nombre' => 'n8n - clientes']);

        $webhook = Webhook::first();
        $this->assertSame($respuesta->json('secreto'), $webhook->secreto);
        $this->assertArrayNotHasKey('secreto', $respuesta->json('row'));
    }

    public function test_valida_eventos_desconocidos(): void
    {
        $this->actor();

        $this->postJson('/admin/integraciones/webhooks', [
            'nombre' => 'n8n',
            'url' => 'https://n8n.midominio.com/webhook/rankpro',
            'eventos' => ['evento.inventado'],
        ])->assertJsonValidationErrors(['eventos.0']);
    }

    public function test_acepta_comodin_de_todos_los_eventos(): void
    {
        $this->actor();

        $this->postJson('/admin/integraciones/webhooks', [
            'nombre' => 'n8n - todo',
            'url' => 'https://n8n.midominio.com/webhook/rankpro',
            'eventos' => ['*'],
        ])->assertCreated();
    }

    public function test_actualiza_webhook(): void
    {
        $this->actor();
        $webhook = Webhook::factory()->create(['nombre' => 'Original']);

        $this->putJson("/admin/integraciones/webhooks/{$webhook->id}", [
            'nombre' => 'Editado',
            'url' => $webhook->url,
            'eventos' => ['ping'],
            'activo' => false,
        ])->assertOk()->assertJsonPath('row.nombre', 'Editado');

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id, 'nombre' => 'Editado', 'activo' => false]);
    }

    public function test_elimina_webhook(): void
    {
        $this->actor();
        $webhook = Webhook::factory()->create();

        $this->deleteJson("/admin/integraciones/webhooks/{$webhook->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('webhooks', ['id' => $webhook->id]);
    }

    public function test_probar_webhook_entrega_correctamente(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actor();
        $webhook = Webhook::factory()->create();

        $this->postJson("/admin/integraciones/webhooks/{$webhook->id}/probar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregado')
            ->assertJsonPath('data.evento', 'ping');
    }

    public function test_probar_webhook_marca_fallo_si_no_responde_bien(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);
        $this->actor();
        $webhook = Webhook::factory()->create();

        $this->postJson("/admin/integraciones/webhooks/{$webhook->id}/probar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente');
    }

    public function test_lista_entregas_paginadas(): void
    {
        $this->actor();
        $webhook = Webhook::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            WebhookEntrega::create([
                'webhook_id' => $webhook->id,
                'evento' => 'ping',
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'payload' => ['id' => 'x', 'evento' => 'ping', 'ocurrido_en' => now()->toIso8601String(), 'datos' => []],
                'estado' => 'pendiente',
            ]);
        }

        $this->getJson("/admin/integraciones/webhooks/{$webhook->id}/entregas")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_reintentar_entrega_fallida(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actor();
        $webhook = Webhook::factory()->create();
        $entrega = WebhookEntrega::create([
            'webhook_id' => $webhook->id,
            'evento' => 'ping',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payload' => ['id' => 'x', 'evento' => 'ping', 'ocurrido_en' => now()->toIso8601String(), 'datos' => []],
            'estado' => 'fallido',
            'intentos' => 6,
        ]);

        $this->postJson("/admin/integraciones/webhooks/entregas/{$entrega->id}/reintentar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregado');
    }

    public function test_no_reintenta_entrega_ya_entregada(): void
    {
        $this->actor();
        $webhook = Webhook::factory()->create();
        $entrega = WebhookEntrega::create([
            'webhook_id' => $webhook->id,
            'evento' => 'ping',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payload' => ['id' => 'x', 'evento' => 'ping', 'ocurrido_en' => now()->toIso8601String(), 'datos' => []],
            'estado' => 'entregado',
        ]);

        $this->postJson("/admin/integraciones/webhooks/entregas/{$entrega->id}/reintentar")
            ->assertStatus(422);
    }
}
