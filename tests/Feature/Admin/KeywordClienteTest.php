<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\KeywordMedicion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordClienteTest extends TestCase
{
    use RefreshDatabase;

    private function lista(Cliente $cliente, string $nombre = 'Lista SEO'): KeywordLista
    {
        return KeywordLista::create(['cliente_id' => $cliente->id, 'nombre' => $nombre, 'canal' => 'seo', 'estado' => 'en_uso']);
    }

    private function keyword(Cliente $cliente, ?KeywordLista $lista, string $texto, array $extra = []): Keyword
    {
        return Keyword::create(array_merge([
            'cliente_id' => $cliente->id,
            'lista_id' => $lista?->id,
            'keyword' => $texto,
            'tipo' => 'principal',
            'estado' => 'en_uso',
        ], $extra));
    }

    private function medir(Keyword $k, string $fecha, int $posicion): void
    {
        KeywordMedicion::create(['keyword_id' => $k->id, 'lista_id' => $k->lista_id, 'fecha' => $fecha, 'posicion' => $posicion]);
    }

    public function test_exige_el_permiso_del_modulo_keywords(): void
    {
        config(['permisos.aplicar' => true]);
        $cliente = Cliente::factory()->create();

        $sin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'operador', 'label' => 'Operador'])->id, 'is_active' => true]);
        $this->actingAs($sin)->get(route('admin.keywords.cliente', $cliente))->assertForbidden();

        $rol = Role::query()->create(['name' => 'seo', 'label' => 'SEO']);
        $rol->permissions()->attach(Permission::query()->firstOrCreate(['name' => 'keywords'], ['label' => 'keywords', 'module' => 'keywords'])->id);
        $con = User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);
        $this->actingAs($con)->get(route('admin.keywords.cliente', $cliente))->assertOk();
    }

    public function test_muestra_contacto_listas_enlaces_y_escapa_el_contenido(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Clínica Peña', 'email' => 'hola@clinica.test']);
        $lista = $this->lista($cliente, 'Lista <b>x</b>');
        $this->keyword($cliente, $lista, 'diseño web');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.keywords.cliente', $cliente))
            ->assertOk()
            ->assertSee('hola@clinica.test')
            ->assertSee('diseño web')
            ->assertSee('Lista &lt;b&gt;x&lt;/b&gt;', false)
            ->assertDontSee('Lista <b>x</b>', false)
            ->assertSee(route('admin.clientes.show', $cliente), false)
            ->assertSee(route('admin.keywords.exportar-lista', $lista), false);
    }

    public function test_kpi_con_mediciones_usa_la_ultima_y_excluye_descartadas(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->lista($cliente, 'A');
        $b = $this->lista($cliente, 'B');

        $k1 = $this->keyword($cliente, $a, 'uno', ['posicion_actual' => 50]);
        $k2 = $this->keyword($cliente, $b, 'dos');
        $k3 = $this->keyword($cliente, $a, 'tres');
        $this->keyword($cliente, $a, 'descartada', ['estado' => 'descartada']);

        // k1: antes 20, ahora 4 -> manda la última medición, no la caché (50).
        $this->medir($k1, '2026-01-01', 20);
        $this->medir($k1, '2026-02-01', 4);
        $this->medir($k2, '2026-02-01', 16);
        // k3 sin medición ni caché: no entra en la posición media.

        $r = $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente));

        $r->assertOk();
        $kpi = $r->viewData('kpi');
        $this->assertSame(3, $kpi['keywords']);
        $this->assertSame(2, $kpi['listas']);
        $this->assertSame(1, $kpi['top10']);
        $this->assertSame(10.0, $kpi['posicion_media']);
        $r->assertSee('+16');
    }

    public function test_sin_mediciones_usa_la_cache_del_banco_o_guion(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $this->keyword($cliente, $lista, 'con cache', ['posicion_actual' => 8, 'posicion_anterior' => 12]);
        $this->keyword($cliente, $lista, 'sin nada');

        $r = $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente));

        $kpi = $r->viewData('kpi');
        $this->assertSame(2, $kpi['keywords']);
        $this->assertSame(1, $kpi['top10']);
        $this->assertSame(8.0, $kpi['posicion_media']);
        $r->assertSee('#12');
    }

    public function test_cliente_sin_keywords_ni_listas_muestra_estado_vacio_sin_dividir_por_cero(): void
    {
        $cliente = Cliente::factory()->create();

        $r = $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente));

        $r->assertOk()->assertSee('Este cliente aún no tiene listas de palabras clave.');
        $kpi = $r->viewData('kpi');
        $this->assertSame(0, $kpi['keywords']);
        $this->assertSame(0, $kpi['listas']);
        $this->assertSame(0, $kpi['top10']);
        $this->assertNull($kpi['posicion_media']);
    }

    public function test_no_mezcla_keywords_de_otro_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();
        $this->keyword($otro, $this->lista($otro, 'Ajena'), 'palabra ajena');

        // No se asierta sobre el HTML: el índice de búsqueda global del layout lista keywords de todos los clientes.
        $r = $this->actingAs(User::factory()->create())->get(route('admin.keywords.cliente', $cliente))->assertOk();
        $this->assertSame(0, $r->viewData('kpi')['keywords']);
        $this->assertCount(0, $r->viewData('listas'));
        $this->assertCount(0, $r->viewData('filasPorLista'));
    }

    public function test_el_banco_enlaza_a_la_vista_nueva(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.keywords.index'))
            ->assertOk()
            ->assertSee(route('admin.keywords.cliente', ['cliente' => '__ID__']), false);
    }
}
