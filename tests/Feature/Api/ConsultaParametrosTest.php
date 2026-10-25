<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

enum SentidoDePrueba: string
{
    case Sube = 'sube';
}

class ConsultaParametrosTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
    }

    public function test_parametros_en_forma_de_array_dan_422_y_no_500(): void
    {
        $this->autenticar();

        foreach (['search[]=a', 'sort[]=id', 'incluir[]=x', 'updated_after[]=2026-01-01', 'created_after[a]=b', 'estado[]=activo'] as $qs) {
            $this->getJson('/api/v1/clientes?'.$qs)->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_texto_enorme_da_422(): void
    {
        $this->autenticar();

        $this->getJson('/api/v1/clientes?search='.str_repeat('a', 5000))->assertStatus(422)->assertJsonValidationErrors('search');
    }

    public function test_fecha_invalida_da_422(): void
    {
        $this->autenticar();

        $this->getJson('/api/v1/clientes?updated_after=no-es-fecha')->assertStatus(422)->assertJsonValidationErrors('updated_after');
    }

    public function test_per_page_raro_cae_al_valor_por_defecto(): void
    {
        $this->autenticar();
        Cliente::factory()->count(3)->create();

        foreach (['abc', '0', '-1', '', '1.5', '[]'] as $valor) {
            $this->getJson('/api/v1/clientes?per_page='.$valor)->assertOk()->assertJsonCount(3, 'data');
        }
        $this->getJson('/api/v1/clientes?per_page[]=1')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/clientes?per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/clientes?per_page=99999')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_per_page_por_defecto_es_50(): void
    {
        $this->autenticar();
        Cliente::factory()->count(51)->create();

        $this->getJson('/api/v1/clientes?per_page=abc')->assertOk()->assertJsonCount(50, 'data');
    }

    public function test_sort_invalido_sigue_dando_422(): void
    {
        $this->autenticar();

        $this->getJson('/api/v1/clientes?sort=columna_inexistente')->assertStatus(422)->assertJsonValidationErrors('sort');
    }

    public function test_429_incluye_retry_after_y_mensaje_en_espanol(): void
    {
        config(['api.rate_limit' => 2]);
        $this->autenticar();

        $this->getJson('/api/v1/clientes')->assertOk();
        $this->getJson('/api/v1/clientes')->assertOk();
        $respuesta = $this->getJson('/api/v1/clientes');

        $respuesta->assertStatus(429)->assertJsonPath('message', 'Demasiadas peticiones. Intenta de nuevo en unos segundos.');
        $this->assertNotNull($respuesta->headers->get('Retry-After'));
    }

    public function test_405_sale_en_espanol_con_allow(): void
    {
        $this->autenticar();

        $respuesta = $this->patchJson('/api/v1/clientes');

        $respuesta->assertStatus(405)->assertJsonPath('message', 'Método no permitido para esta ruta.');
        $this->assertNotNull($respuesta->headers->get('Allow'));
    }

    public function test_json_malformado_da_400(): void
    {
        $this->autenticar();

        $this->call('POST', '/api/v1/clientes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"nombre": "x",')
            ->assertStatus(400)
            ->assertJsonPath('message', 'JSON inválido: el cuerpo de la petición no se pudo interpretar.');
    }

    public function test_cuerpo_json_vacio_o_get_no_se_confunden_con_json_roto(): void
    {
        $this->autenticar();

        $cabeceras = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        // Vacío en POST: sigue la validación normal (422), no el 400.
        $this->call('POST', '/api/v1/clientes', [], [], [], $cabeceras, '')->assertStatus(422);
        $this->call('GET', '/api/v1/clientes', [], [], [], $cabeceras, '')->assertOk();
        $this->postJson('/api/v1/clientes', ['nombre' => 'Valido', 'estado' => 'activo'])->assertCreated();
    }

    public function test_regla_enum_y_attributes_en_espanol(): void
    {
        $v = Validator::make(['sentido' => 'otro'], ['sentido' => [Rule::enum(SentidoDePrueba::class)]]);

        $this->assertSame('El valor seleccionado para sentido no es válido.', $v->errors()->first('sentido'));

        $v = Validator::make([], ['cliente_id' => 'required']);
        $this->assertSame('El campo cliente es obligatorio.', $v->errors()->first('cliente_id'));
    }
}
