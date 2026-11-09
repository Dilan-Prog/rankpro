<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordExportTest extends TestCase
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

    public function test_exporta_las_keywords_de_una_lista_en_csv_utf8_con_bom(): void
    {
        $cliente = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Clínica Peña']);
        $lista = $this->lista($cliente);
        $otra = $this->lista($cliente, 'Otra lista');
        $this->keyword($cliente, $lista, 'diseño web en león', ['volumen_busqueda' => 320, 'posicion_actual' => 7]);
        $this->keyword($cliente, $otra, 'keyword de otra lista');

        $respuesta = $this->actingAs(User::factory()->create())->get(route('admin.keywords.exportar-lista', $lista));

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));

        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Keyword', $csv);
        $this->assertStringContainsString('diseño web en león', $csv);
        $this->assertStringContainsString('Clínica Peña', $csv);
        $this->assertStringContainsString('320', $csv);
        $this->assertStringNotContainsString('keyword de otra lista', $csv);
    }

    public function test_exporta_las_listas_seleccionadas_y_solo_esas(): void
    {
        $cliente = Cliente::factory()->create();
        $a = $this->lista($cliente, 'A');
        $b = $this->lista($cliente, 'B');
        $c = $this->lista($cliente, 'C');
        $this->keyword($cliente, $a, 'palabra de a');
        $this->keyword($cliente, $b, 'palabra de b');
        $this->keyword($cliente, $c, 'palabra de c');

        $csv = $this->actingAs(User::factory()->create())
            ->get(route('admin.keywords.exportar-seleccion', ['listas' => $a->id.','.$c->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('palabra de a', $csv);
        $this->assertStringContainsString('palabra de c', $csv);
        $this->assertStringNotContainsString('palabra de b', $csv);
    }

    public function test_la_seleccion_exige_listas_validas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.keywords.exportar-seleccion'))->assertStatus(302);
        $this->actingAs($user)->get(route('admin.keywords.exportar-seleccion', ['listas' => 'x,0,-3']))->assertStatus(422);
    }

    public function test_protege_las_celdas_que_empiezan_como_formula(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $this->keyword($cliente, $lista, '=HYPERLINK("http://malo.test")');
        $this->keyword($cliente, $lista, '+cmd|calc');
        $this->keyword($cliente, $lista, '-2+3');
        $this->keyword($cliente, $lista, '@SUMA(1;1)');
        $this->keyword($cliente, $lista, 'normal', ['url_asignada' => '=1+1']);

        $csv = $this->actingAs(User::factory()->create())
            ->get(route('admin.keywords.exportar-lista', $lista))
            ->streamedContent();

        // Las filas se parsean para comparar el valor real de la celda, no el escapado del CSV.
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($csv, 3));
        rewind($handle);
        $celdas = [];
        while (($fila = fgetcsv($handle)) !== false) {
            $celdas[] = $fila[2] ?? null;
            $celdas[] = $fila[8] ?? null;
        }

        $this->assertContains("'=HYPERLINK(\"http://malo.test\")", $celdas);
        $this->assertContains("'+cmd|calc", $celdas);
        $this->assertContains("'-2+3", $celdas);
        $this->assertContains("'@SUMA(1;1)", $celdas);
        $this->assertContains("'=1+1", $celdas);
        $this->assertContains('normal', $celdas);
    }

    public function test_una_lista_eliminada_no_se_exporta(): void
    {
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);
        $lista->delete();

        $this->actingAs(User::factory()->create())->get(route('admin.keywords.exportar-lista', $lista->id))->assertNotFound();
    }

    public function test_exige_el_permiso_del_modulo_keywords(): void
    {
        config(['permisos.aplicar' => true]);
        $cliente = Cliente::factory()->create();
        $lista = $this->lista($cliente);

        $sinPermiso = User::factory()->create(['role_id' => Role::query()->create(['name' => 'operador', 'label' => 'Operador'])->id, 'is_active' => true]);
        $this->actingAs($sinPermiso)->get(route('admin.keywords.exportar-lista', $lista))->assertForbidden();
        $this->actingAs($sinPermiso)->get(route('admin.keywords.exportar-seleccion', ['listas' => (string) $lista->id]))->assertForbidden();

        $rol = Role::query()->create(['name' => 'seo', 'label' => 'SEO']);
        $rol->permissions()->attach(Permission::query()->firstOrCreate(['name' => 'keywords'], ['label' => 'keywords', 'module' => 'keywords'])->id);
        $conPermiso = User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);

        $this->actingAs($conPermiso)->get(route('admin.keywords.exportar-lista', $lista))->assertOk();
    }

    public function test_requiere_sesion(): void
    {
        $lista = $this->lista(Cliente::factory()->create());

        $this->get(route('admin.keywords.exportar-lista', $lista))->assertRedirect();
    }
}
