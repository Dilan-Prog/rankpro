<?php

namespace Tests\Feature\Api;

use App\Models\AdsConversion;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El endpoint público de conversiones guarda `metadata` tal cual: tiene tope
 * de claves y de tamaño para que un POST no pueda inflar la tabla.
 */
class TrackingMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function enviar(array $metadata): \Illuminate\Testing\TestResponse
    {
        $cliente = Cliente::factory()->create();
        $cliente->forceFill(['api_token' => 'rp_live_'.Str::random(56)])->save();

        return $this->postJson('/api/tracking/conversion', [
            'visitor_id' => 'visitante-1', 'tipo' => 'whatsapp', 'valor' => 10, 'metadata' => $metadata,
        ], ['X-RankPro-Token' => $cliente->api_token]);
    }

    public function test_metadata_razonable_se_acepta(): void
    {
        $this->enviar(['pagina' => 'servicios', 'boton' => 'hero'])->assertSuccessful();

        $this->assertSame(1, AdsConversion::count());
    }

    public function test_metadata_con_mas_de_20_claves_responde_422(): void
    {
        $meta = [];
        for ($i = 0; $i < 21; $i++) {
            $meta["k{$i}"] = 'v';
        }

        $this->enviar($meta)->assertStatus(422)->assertJsonValidationErrors('metadata');
        $this->assertSame(0, AdsConversion::count());
    }

    public function test_metadata_de_mas_de_2kb_responde_422(): void
    {
        $this->enviar(['texto' => str_repeat('a', 3000)])->assertStatus(422)->assertJsonValidationErrors('metadata');
        $this->assertSame(0, AdsConversion::count());
    }
}
