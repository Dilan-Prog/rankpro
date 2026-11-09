<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El banco de keywords obedece al cliente activo del menú: con uno elegido solo
 * muestra sus listas y keywords; sin él, todo como siempre.
 */
class KeywordsClienteActivoTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(): array
    {
        $a = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Clinica Alfa']);
        $b = Cliente::factory()->create(['empresa' => null, 'nombre' => 'Hotel Beta']);

        $listaA = KeywordLista::create(['cliente_id' => $a->id, 'nombre' => 'Lista de Alfa', 'canal' => 'seo', 'estado' => 'en_uso']);
        $listaB = KeywordLista::create(['cliente_id' => $b->id, 'nombre' => 'Lista de Beta', 'canal' => 'seo', 'estado' => 'en_uso']);

        Keyword::create(['cliente_id' => $a->id, 'lista_id' => $listaA->id, 'keyword' => 'kw alfa con lista', 'tipo' => 'principal', 'estado' => 'en_uso']);
        Keyword::create(['cliente_id' => $b->id, 'lista_id' => $listaB->id, 'keyword' => 'kw beta con lista', 'tipo' => 'principal', 'estado' => 'en_uso']);
        Keyword::create(['cliente_id' => $a->id, 'keyword' => 'kw alfa suelta', 'tipo' => 'secundaria', 'estado' => 'seguimiento']);
        Keyword::create(['cliente_id' => $b->id, 'keyword' => 'kw beta suelta', 'tipo' => 'secundaria', 'estado' => 'seguimiento']);

        return [User::factory()->create(), $a, $b, $listaA, $listaB];
    }

    public function test_con_cliente_activo_solo_aparece_lo_de_ese_cliente(): void
    {
        [$user, $a] = $this->escenario();

        $this->actingAs($user)->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.keywords.index'))
            ->assertOk()
            ->assertSee('Lista de Alfa')
            ->assertSee('kw alfa suelta')
            ->assertDontSee('Lista de Beta')
            // El HTML no sirve para las keywords sueltas de otro cliente: la paleta de comandos del layout
            // (global, fuera de este modulo) las lista todas; se comprueba lo que recibe la vista.
            ->assertViewHas('keywordsSinLista', fn ($k) => $k->pluck('keyword')->all() === ['kw alfa suelta'])
            ->assertViewHas('listas', fn ($l) => $l->count() === 1)
            ->assertSee('1 lista de palabras clave · 2 keywords en total')
            ->assertViewHas('clienteActivo', fn ($c) => $c->id === $a->id);
    }

    public function test_sin_cliente_activo_aparece_todo(): void
    {
        [$user] = $this->escenario();

        $this->actingAs($user)->get(route('admin.keywords.index'))
            ->assertOk()
            ->assertSee('Lista de Alfa')
            ->assertSee('Lista de Beta')
            ->assertSee('kw alfa suelta')
            ->assertSee('kw beta suelta')
            ->assertSee('2 listas de palabras clave · 4 keywords en total');
    }

    public function test_el_aviso_solo_aparece_con_cliente_activo(): void
    {
        [$user, $a] = $this->escenario();

        $this->actingAs($user)->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.keywords.index'))
            ->assertSee('aviso-cliente', false)
            ->assertSee('el cliente activo del menú');

        // La sesion de la peticion anterior seguiria viva en la prueba.
        $this->flushSession();
        $this->actingAs($user)->get(route('admin.keywords.index'))
            ->assertDontSee('aviso-cliente', false);
    }

    public function test_los_formularios_preseleccionan_al_cliente_activo(): void
    {
        [$user, , $b] = $this->escenario();

        $html = $this->actingAs($user)->withSession(['cliente_activo_id' => $b->id])
            ->get(route('admin.keywords.index'))->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$b->id.'"\s+selected[^>]*>Hotel Beta/', $html);
        $this->assertGreaterThanOrEqual(3, preg_match_all('/<option value="'.$b->id.'"\s+selected/', $html));
    }

    public function test_exportar_seleccion_se_limita_al_cliente_activo(): void
    {
        [$user, $a, , $listaA, $listaB] = $this->escenario();

        $csv = $this->actingAs($user)->withSession(['cliente_activo_id' => $a->id])
            ->get(route('admin.keywords.exportar-seleccion', ['listas' => $listaA->id.','.$listaB->id]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('kw alfa con lista', $csv);
        $this->assertStringNotContainsString('kw beta con lista', $csv);
    }
}
