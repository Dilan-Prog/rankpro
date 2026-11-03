<?php

namespace Tests\Feature\Api;

use App\Events\EventoWebhook;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Idempotencia del seguimiento público: un reintento del mismo evento_id no
 * debe contarse como clic/conversión nuevo ni repetir webhooks.
 */
class TrackingIdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private function clienteConToken(): Cliente
    {
        $cliente = Cliente::factory()->create();
        $cliente->forceFill(['api_token' => 'rp_live_'.Str::random(56)])->save();

        return $cliente;
    }

    public function payloadClic(array $extra = []): array
    {
        return array_merge([
            'visitor_id' => 'visitante-1',
            'gclid' => 'gclid-abc',
            'landing_url' => 'https://ejemplo.com/?gclid=gclid-abc',
        ], $extra);
    }

    public function payloadConversion(array $extra = []): array
    {
        return array_merge(['visitor_id' => 'visitante-1', 'tipo' => 'whatsapp', 'valor' => 100], $extra);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function eventos(): array
    {
        return [
            'clic' => ['/api/tracking/clic', 'payloadClic', AdsClic::class],
            'conversion' => ['/api/tracking/conversion', 'payloadConversion', AdsConversion::class],
        ];
    }

    /** @dataProvider eventos */
    public function test_mismo_evento_id_dos_veces_guarda_una_sola_fila(string $ruta, string $payload, string $modelo): void
    {
        $cliente = $this->clienteConToken();
        $evento = (string) Str::uuid();
        $h = ['X-RankPro-Token' => $cliente->api_token];

        $primera = $this->postJson($ruta, $this->$payload(['evento_id' => $evento]), $h);
        $segunda = $this->postJson($ruta, $this->$payload(['evento_id' => $evento]), $h);

        $primera->assertStatus(201)->assertJson(['ok' => true])->assertJsonMissingPath('duplicado');
        $segunda->assertStatus(201)->assertJson(['ok' => true, 'duplicado' => true])->assertHeader('X-RankPro-Duplicado', '1');
        $this->assertSame(1, $modelo::count());
        $this->assertSame($evento, $modelo::first()->evento_id);
    }

    /** @dataProvider eventos */
    public function test_el_duplicado_no_vuelve_a_disparar_webhooks(string $ruta, string $payload, string $modelo): void
    {
        $cliente = $this->clienteConToken();
        $h = ['X-RankPro-Token' => $cliente->api_token];
        $body = $this->$payload(['evento_id' => 'evento-reintento-0001']);

        Event::fake([EventoWebhook::class]);
        $this->postJson($ruta, $body, $h)->assertStatus(201);
        $this->postJson($ruta, $body, $h)->assertStatus(201);

        Event::assertDispatchedTimes(EventoWebhook::class, 1);
    }

    /** @dataProvider eventos */
    public function test_sin_evento_id_se_comporta_como_antes(string $ruta, string $payload, string $modelo): void
    {
        $cliente = $this->clienteConToken();
        $h = ['X-RankPro-Token' => $cliente->api_token];

        $this->postJson($ruta, $this->$payload(), $h)->assertStatus(201)->assertExactJson(['ok' => true]);
        $this->postJson($ruta, $this->$payload(['evento_id' => '']), $h)->assertStatus(201)->assertExactJson(['ok' => true]);

        $this->assertSame(2, $modelo::count());
        $this->assertNull($modelo::first()->evento_id);
    }

    /** @dataProvider eventos */
    public function test_mismo_evento_id_en_dos_clientes_son_dos_eventos(string $ruta, string $payload, string $modelo): void
    {
        $a = $this->clienteConToken();
        $b = $this->clienteConToken();
        $body = $this->$payload(['evento_id' => 'evento-compartido-01']);

        $this->postJson($ruta, $body, ['X-RankPro-Token' => $a->api_token])->assertStatus(201)->assertJsonMissingPath('duplicado');
        $this->postJson($ruta, $body, ['X-RankPro-Token' => $b->api_token])->assertStatus(201)->assertJsonMissingPath('duplicado');

        $this->assertSame(2, $modelo::count());
        $this->assertSame(1, $modelo::where('cliente_id', $a->id)->count());
        $this->assertSame(1, $modelo::where('cliente_id', $b->id)->count());
    }

    /** @dataProvider eventos */
    public function test_el_header_gana_sobre_el_cuerpo(string $ruta, string $payload, string $modelo): void
    {
        $cliente = $this->clienteConToken();
        $h = ['X-RankPro-Token' => $cliente->api_token, 'X-RankPro-Evento-Id' => 'evento-del-header-01'];

        $this->postJson($ruta, $this->$payload(['evento_id' => 'evento-del-cuerpo-01']), $h)->assertStatus(201);
        // Mismo header con otro cuerpo: es el mismo evento.
        $this->postJson($ruta, $this->$payload(['evento_id' => 'evento-otro-cuerpo-1']), $h)
            ->assertStatus(201)->assertJson(['duplicado' => true]);

        $this->assertSame(1, $modelo::count());
        $this->assertSame('evento-del-header-01', $modelo::first()->evento_id);
    }

    /** @dataProvider eventos */
    public function test_evento_id_mal_formado_da_422_y_no_guarda(string $ruta, string $payload, string $modelo): void
    {
        $cliente = $this->clienteConToken();
        $h = ['X-RankPro-Token' => $cliente->api_token];

        foreach (['corto', str_repeat('a', 65), 'tiene espacios en el id', 'con/barra/invalida00'] as $malo) {
            $this->postJson($ruta, $this->$payload(['evento_id' => $malo]), $h)
                ->assertStatus(422)->assertJsonValidationErrors('evento_id');
        }

        $this->postJson($ruta, $this->$payload(), $h + ['X-RankPro-Evento-Id' => 'x y'])
            ->assertStatus(422)->assertJsonValidationErrors('evento_id');

        $this->assertSame(0, $modelo::count());
    }

    public function test_la_violacion_del_indice_unico_en_una_carrera_se_responde_como_duplicado(): void
    {
        $cliente = $this->clienteConToken();
        $h = ['X-RankPro-Token' => $cliente->api_token];

        // Simula que otra petición insertó entre la consulta previa y el INSERT.
        $hecho = false;
        AdsClic::creating(function (AdsClic $clic) use (&$hecho) {
            if ($hecho) {
                return;
            }
            $hecho = true;
            AdsClic::withoutEvents(fn () => AdsClic::create([
                'cliente_id' => $clic->cliente_id,
                'evento_id' => $clic->evento_id,
                'visitor_id' => 'otro',
                'landing_url' => 'https://x.test',
            ]));
        });

        $this->postJson('/api/tracking/clic', $this->payloadClic(['evento_id' => 'evento-carrera-00001']), $h)
            ->assertStatus(201)->assertJson(['ok' => true, 'duplicado' => true]);

        $this->assertSame(1, AdsClic::count());
    }

    public function test_el_snippet_genera_y_reutiliza_el_evento_id(): void
    {
        $cliente = $this->clienteConToken();

        $js = $this->get('/api/tracking/snippet/'.$cliente->api_token.'.js')->assertOk()->getContent();

        $this->assertStringContainsString('randomUUID', $js);
        $this->assertStringContainsString('getRandomValues', $js);
        $this->assertStringContainsString('payload.evento_id = uuid()', $js);
        $this->assertStringContainsString('X-RankPro-Evento-Id', $js);
        $this->assertStringContainsString('post(path, payload, intento + 1)', $js);
    }

    public function test_snippet_con_token_invalido_sigue_inerte_con_200(): void
    {
        $js = $this->get('/api/tracking/snippet/token-que-no-existe.js')->assertOk()->getContent();

        $this->assertStringContainsString('snippet inactivo', $js);
    }
}
