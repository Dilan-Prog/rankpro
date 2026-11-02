<?php

namespace Tests\Feature\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\Cliente;
use App\Models\ElementoContenido;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\Contenido\PizarraContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** Módulo «Contenido» → Pizarra: estructura validada, aislamiento por cliente, plantillas y escape. */
class ContenidoPizarraTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // pizarra.css/js aún no están en public/build (se compila al final); el resto de la vista sí se prueba.
        $this->withoutVite();

        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->create();
    }

    private function como(?Cliente $cliente = null)
    {
        return $this->actingAs($this->usuario)->withSession([ClienteActivo::CLAVE => ($cliente ?? $this->cliente)->id]);
    }

    private function pizarra(array $datos = [], ?Cliente $cliente = null, array $extra = []): ElementoContenido
    {
        return ElementoContenido::create($extra + [
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'tipo' => TipoElementoContenido::Pizarra->value,
            'nombre' => 'Mi pizarra',
            'contenido' => json_encode($datos ?: PizarraContenido::vacia(), JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function nota(string $id = 'n1', array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'tipo' => 'nota', 'x' => 10, 'y' => 20, 'ancho' => 160, 'alto' => 110,
            'texto' => 'Hola', 'color' => '#FDE68A', 'desde' => null, 'hasta' => null,
        ];
    }

    private function flecha(string $id, $desde, $hasta): array
    {
        return ['id' => $id, 'tipo' => 'flecha', 'color' => '#64748B', 'desde' => $desde, 'hasta' => $hasta];
    }

    // ---------- Estructura y límites ----------

    public function test_normaliza_una_pizarra_valida_y_descarta_lo_que_no_es_de_la_estructura(): void
    {
        $resultado = PizarraContenido::normalizar([
            'elementos' => [
                $this->nota('a', ['intruso' => '<b>x</b>', 'color' => '#fde68a']),
                $this->nota('b'),
                $this->flecha('f', 'a', ['x' => 50.26, 'y' => -3]),
            ],
            'vista' => ['x' => 5, 'y' => 6, 'zoom' => 1.5, 'otra' => 1],
        ]);

        $this->assertCount(3, $resultado['elementos']);
        $this->assertArrayNotHasKey('intruso', $resultado['elementos'][0]);
        $this->assertSame('#FDE68A', $resultado['elementos'][0]['color']);
        $this->assertSame('a', $resultado['elementos'][2]['desde']);
        $this->assertSame(['x' => 50.3, 'y' => -3], $resultado['elementos'][2]['hasta']);
        $this->assertSame(['x' => 5, 'y' => 6, 'zoom' => 1.5], $resultado['vista']);
    }

    /** @dataProvider casosInvalidos */
    public function test_rechaza_estructuras_invalidas(callable $armar): void
    {
        $datos = ['elementos' => [$this->nota('a'), $this->nota('b')], 'vista' => ['x' => 0, 'y' => 0, 'zoom' => 1]];
        $armar($datos, $this);

        $this->expectException(InvalidArgumentException::class);
        PizarraContenido::normalizar($datos);
    }

    public static function casosInvalidos(): array
    {
        return [
            'tipo inexistente' => [function (&$d, $t) { $d['elementos'][0]['tipo'] = 'imagen'; }],
            'id con caracteres raros' => [function (&$d, $t) { $d['elementos'][0]['id'] = 'a"><img>'; }],
            'ids repetidos' => [function (&$d, $t) { $d['elementos'][1]['id'] = 'a'; }],
            'x fuera de rango' => [function (&$d, $t) { $d['elementos'][0]['x'] = 20001; }],
            'y no numerica' => [function (&$d, $t) { $d['elementos'][0]['y'] = 'abc'; }],
            'ancho muy chico' => [function (&$d, $t) { $d['elementos'][0]['ancho'] = 5; }],
            'alto muy grande' => [function (&$d, $t) { $d['elementos'][0]['alto'] = 4001; }],
            'texto de 2001 caracteres' => [function (&$d, $t) { $d['elementos'][0]['texto'] = str_repeat('a', 2001); }],
            'texto como arreglo' => [function (&$d, $t) { $d['elementos'][0]['texto'] = ['x']; }],
            'color no hex' => [function (&$d, $t) { $d['elementos'][0]['color'] = 'red'; }],
            'color con inyeccion css' => [function (&$d, $t) { $d['elementos'][0]['color'] = '#fff;background:url(x)'; }],
            'flecha a un id que no existe' => [function (&$d, $t) { $d['elementos'][] = $t->flecha('f', 'a', 'zzz'); }],
            'flecha a si misma' => [function (&$d, $t) { $d['elementos'][] = $t->flecha('f', 'f', 'a'); }],
            'flecha que apunta a otra flecha' => [function (&$d, $t) { $d['elementos'][] = $t->flecha('f', 'a', 'b'); $d['elementos'][] = $t->flecha('g', 'f', 'a'); }],
            'flecha sin extremo' => [function (&$d, $t) { $d['elementos'][] = $t->flecha('f', 'a', null); }],
            'punto de flecha fuera de rango' => [function (&$d, $t) { $d['elementos'][] = $t->flecha('f', 'a', ['x' => 99999, 'y' => 0]); }],
            'zoom fuera de rango' => [function (&$d, $t) { $d['vista']['zoom'] = 9; }],
            'elementos no es lista' => [function (&$d, $t) { $d['elementos'] = ['a' => $t->nota('a')]; }],
        ];
    }

    public function test_el_limite_es_de_500_elementos(): void
    {
        $con = fn (int $n) => ['elementos' => array_map(fn ($i) => $this->nota('n'.$i), range(1, $n))];

        $this->assertCount(500, PizarraContenido::normalizar($con(500))['elementos']);

        $this->expectException(InvalidArgumentException::class);
        PizarraContenido::normalizar($con(501));
    }

    public function test_texto_de_2000_caracteres_es_valido_y_se_conserva_literal(): void
    {
        $texto = str_repeat('á', 1990).'<b>&</b>';
        $nota = PizarraContenido::normalizar(['elementos' => [$this->nota('a', ['texto' => $texto])]])['elementos'][0];

        $this->assertSame($texto, $nota['texto']);
    }

    public function test_decodificar_tolera_contenido_vacio_o_roto(): void
    {
        $this->assertSame(PizarraContenido::vacia(), PizarraContenido::decodificar(null));
        $this->assertSame(PizarraContenido::vacia(), PizarraContenido::decodificar('{no es json'));
        $this->assertSame(PizarraContenido::vacia(), PizarraContenido::decodificar('{"elementos":[{"id":"a","tipo":"zzz"}]}'));
    }

    // ---------- Plantillas y creación ----------

    public function test_las_plantillas_son_validas_segun_las_reglas_de_la_propia_clase(): void
    {
        foreach ([null, 'lluvia_ideas', 'flujo'] as $nombre) {
            $plantilla = PizarraContenido::plantilla($nombre);
            $this->assertSame($plantilla, PizarraContenido::normalizar($plantilla), "Plantilla {$nombre}");
        }

        $this->assertCount(0, PizarraContenido::plantilla(null)['elementos']);
        $this->assertCount(13, PizarraContenido::plantilla('lluvia_ideas')['elementos']);
        $this->assertCount(5, PizarraContenido::plantilla('flujo')['elementos']);
    }

    public function test_crea_pizarras_en_blanco_y_con_plantilla_desde_el_explorador(): void
    {
        foreach ([[null, 0], ['lluvia_ideas', 13], ['flujo', 5]] as [$plantilla, $cantidad]) {
            $res = $this->como()->postJson(route('admin.contenido.store'), [
                'tipo' => 'pizarra', 'nombre' => 'Tablero', 'plantilla' => $plantilla,
            ])->assertCreated();

            $elemento = ElementoContenido::findOrFail($res->json('id'));
            $this->assertSame(TipoElementoContenido::Pizarra, $elemento->tipo);
            $this->assertSame($this->cliente->id, $elemento->cliente_id);
            $this->assertCount($cantidad, PizarraContenido::decodificar($elemento->contenido)['elementos']);
        }
    }

    public function test_rechaza_una_plantilla_que_no_existe(): void
    {
        $this->como()->postJson(route('admin.contenido.store'), ['tipo' => 'pizarra', 'nombre' => 'X', 'plantilla' => 'kanban'])
            ->assertStatus(422);
    }

    public function test_el_menu_crear_y_el_listado_muestran_la_pizarra(): void
    {
        $this->pizarra();

        $html = $this->como()->get(route('admin.contenido.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-cm-crear="pizarra"', $html);
        $this->assertStringContainsString('data-plantilla="lluvia_ideas"', $html);
        $this->assertStringContainsString('data-plantilla="flujo"', $html);
        $this->assertStringContainsString('Mi pizarra', $html);
        $this->assertStringContainsString('fa-chalkboard', $html);
    }

    public function test_abrir_el_elemento_lleva_a_la_pantalla_de_pizarra(): void
    {
        $p = $this->pizarra();

        $this->como()->get(route('admin.contenido.show', $p))->assertRedirect(route('admin.contenido.pizarra', $p));
    }

    // ---------- Pantalla y aislamiento por cliente ----------

    public function test_abre_la_pantalla_de_la_pizarra(): void
    {
        $p = $this->pizarra(['elementos' => [$this->nota('a')]]);

        $this->como()->get(route('admin.contenido.pizarra', $p))
            ->assertOk()
            ->assertSee('data-pz-lienzo', false)
            ->assertSee(route('admin.contenido.pizarra.guardar', $p), false);
    }

    public function test_una_pizarra_de_otro_cliente_responde_404(): void
    {
        $otro = Cliente::factory()->create();
        $ajena = $this->pizarra([], $otro);

        $this->como()->get(route('admin.contenido.pizarra', $ajena))->assertNotFound();
        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $ajena), PizarraContenido::vacia())->assertNotFound();
    }

    public function test_solo_las_pizarras_tienen_pantalla_de_pizarra(): void
    {
        $doc = ElementoContenido::create([
            'cliente_id' => $this->cliente->id, 'tipo' => 'documento', 'nombre' => 'Doc', 'contenido' => '',
        ]);

        $this->como()->get(route('admin.contenido.pizarra', $doc))->assertNotFound();
        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $doc), PizarraContenido::vacia())->assertNotFound();
    }

    public function test_los_invitados_no_entran(): void
    {
        $p = $this->pizarra();

        $this->get(route('admin.contenido.pizarra', $p))->assertRedirect();
        $this->putJson(route('admin.contenido.pizarra.guardar', $p), PizarraContenido::vacia())->assertUnauthorized();
    }

    // ---------- Guardado ----------

    public function test_guarda_la_pizarra_completa(): void
    {
        $p = $this->pizarra();
        $datos = [
            'elementos' => [$this->nota('a'), $this->nota('b', ['x' => 300]), $this->flecha('f', 'a', 'b')],
            'vista' => ['x' => -40, 'y' => 12, 'zoom' => 0.8],
        ];

        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $p), $datos)
            ->assertOk()
            ->assertJsonPath('datos.elementos.2.hasta', 'b');

        $guardado = json_decode($p->fresh()->contenido, true);
        $this->assertCount(3, $guardado['elementos']);
        $this->assertSame(0.8, $guardado['vista']['zoom']);
        $this->assertSame('b', $guardado['elementos'][2]['hasta']);
    }

    public function test_guardar_rechaza_una_estructura_invalida_y_no_toca_lo_guardado(): void
    {
        $p = $this->pizarra(['elementos' => [$this->nota('a')]]);
        $antes = $p->contenido;

        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $p), [
            'elementos' => [$this->nota('a', ['texto' => str_repeat('x', 2001)])],
        ])->assertStatus(422)->assertJsonStructure(['message']);

        $this->assertSame($antes, $p->fresh()->contenido);
    }

    public function test_guardar_rechaza_mas_de_500_elementos(): void
    {
        $p = $this->pizarra();
        $elementos = array_map(fn ($i) => $this->nota('n'.$i), range(1, 501));

        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $p), ['elementos' => $elementos])->assertStatus(422);
    }

    public function test_un_cuerpo_gigante_se_rechaza_con_413_sin_procesarlo(): void
    {
        $p = $this->pizarra();

        $this->como()->putJson(route('admin.contenido.pizarra.guardar', $p), [
            'elementos' => [], 'basura' => str_repeat('a', 2600000),
        ])->assertStatus(413);
    }

    // ---------- Escape ----------

    public function test_el_texto_y_el_nombre_se_entregan_escapados(): void
    {
        $p = $this->pizarra(
            ['elementos' => [$this->nota('a', ['texto' => '<script>alert(1)</script><img src=x onerror=alert(2)>'])]],
            null,
            ['nombre' => '<b>Nombre</b>']
        );

        $html = $this->como()->get(route('admin.contenido.pizarra', $p))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Nombre</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)', $html);
    }

    public function test_el_cliente_js_no_usa_innerhtml(): void
    {
        $js = file_get_contents(base_path('resources/js/pizarra.js'));

        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('insertAdjacentHTML', $js);
    }
}
