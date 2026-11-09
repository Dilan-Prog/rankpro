<?php

namespace Tests\Feature\Admin;

use App\Models\AdsConversion;
use App\Models\AdsEmbudoEtapa;
use App\Models\Cliente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla /admin/conversiones: clasificación y marcado de duplicadas en lote, exclusión de las
 * duplicadas en listas / embudo / exportes, y exporte a Google Ads solo de lo clasificado y con click id.
 */
class ConversionesLoteTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->create();
    }

    private function conv(array $extra = [], ?Cliente $cliente = null): AdsConversion
    {
        $creada = $extra['created_at'] ?? now();
        unset($extra['created_at']);

        $c = AdsConversion::create($extra + [
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'visitor_id' => 'v-'.uniqid(),
            'gclid' => 'gclid-'.uniqid(),
            'tipo' => 'whatsapp',
            'estado' => 'pendiente',
        ]);
        $c->forceFill(['created_at' => $creada])->save();

        return $c;
    }

    private function etapa(Cliente $cliente, string $nombre = 'Cotización'): AdsEmbudoEtapa
    {
        return AdsEmbudoEtapa::create(['cliente_id' => $cliente->id, 'nombre' => $nombre, 'orden' => 1]);
    }

    private function csv(string $contenido): array
    {
        return array_values(array_filter(preg_split('/\R/', $contenido)));
    }

    // --- Clasificación en lote ----------------------------------------------------------------

    public function test_clasifica_varias_conversiones_en_una_etapa_del_mismo_cliente(): void
    {
        $etapa = $this->etapa($this->cliente);
        $a = $this->conv();
        $b = $this->conv();
        $fuera = $this->conv();

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id, $b->id], 'ads_embudo_etapa_id' => $etapa->id])
            ->assertOk()
            ->assertJsonPath('actualizadas', 2);

        $this->assertSame($etapa->id, $a->fresh()->ads_embudo_etapa_id);
        $this->assertSame($etapa->id, $b->fresh()->ads_embudo_etapa_id);
        $this->assertNull($fuera->fresh()->ads_embudo_etapa_id);
    }

    public function test_desde_el_formulario_redirige_con_el_resultado_visible(): void
    {
        $etapa = $this->etapa($this->cliente);
        $a = $this->conv();

        $this->actingAs($this->usuario)
            ->from(route('admin.conversiones.index'))
            ->post(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id], 'ads_embudo_etapa_id' => $etapa->id])
            ->assertRedirect(route('admin.conversiones.index'))
            ->assertSessionHas('status', '1 conversión(es) clasificadas como «Cotización».');
    }

    public function test_quitar_etapa_las_deja_sin_clasificar_pero_una_etapa_vacia_no_borra(): void
    {
        $etapa = $this->etapa($this->cliente);
        $a = $this->conv(['ads_embudo_etapa_id' => $etapa->id]);

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id]])
            ->assertStatus(422)->assertJsonValidationErrors('ads_embudo_etapa_id');
        $this->assertSame($etapa->id, $a->fresh()->ads_embudo_etapa_id);

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id], 'quitar_etapa' => true])
            ->assertOk();
        $this->assertNull($a->fresh()->ads_embudo_etapa_id);
    }

    public function test_rechaza_una_etapa_de_otro_cliente(): void
    {
        $otro = Cliente::factory()->create();
        $etapaAjena = $this->etapa($otro);
        $a = $this->conv();

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id], 'ads_embudo_etapa_id' => $etapaAjena->id])
            ->assertStatus(422)->assertJsonValidationErrors('ads_embudo_etapa_id');

        $this->assertNull($a->fresh()->ads_embudo_etapa_id);
    }

    public function test_rechaza_conversiones_de_varios_clientes_sin_tocar_ninguna(): void
    {
        $otro = Cliente::factory()->create();
        $etapa = $this->etapa($this->cliente);
        $a = $this->conv();
        $b = $this->conv([], $otro);

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id, $b->id], 'ads_embudo_etapa_id' => $etapa->id])
            ->assertStatus(422)->assertJsonValidationErrors('conversion_ids');

        $this->assertNull($a->fresh()->ads_embudo_etapa_id);
        $this->assertNull($b->fresh()->ads_embudo_etapa_id);
    }

    public function test_rechaza_seleccion_vacia_o_con_ids_inexistentes(): void
    {
        $etapa = $this->etapa($this->cliente);
        $a = $this->conv();

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [], 'ads_embudo_etapa_id' => $etapa->id])
            ->assertStatus(422);
        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id, 999999], 'ads_embudo_etapa_id' => $etapa->id])
            ->assertStatus(422)->assertJsonValidationErrors('conversion_ids');
        $this->assertNull($a->fresh()->ads_embudo_etapa_id);
    }

    // --- Duplicadas a mano -----------------------------------------------------------------------

    public function test_marca_a_mano_conservando_la_mas_antigua_y_se_puede_desmarcar(): void
    {
        $vieja = $this->conv(['created_at' => now()->subHour()]);
        $a = $this->conv(['created_at' => now()->subMinutes(30)]);
        $b = $this->conv(['created_at' => now()]);

        $this->actingAs($this->usuario)
            ->postJson(route('admin.conversiones.lote.duplicadas'), ['conversion_ids' => [$b->id, $vieja->id, $a->id]])
            ->assertOk()->assertJsonPath('original_id', $vieja->id)->assertJsonPath('marcadas', 2);

        $this->assertNull($vieja->fresh()->duplicada_de_id);
        $this->assertSame($vieja->id, $a->fresh()->duplicada_de_id);
        $this->assertSame($vieja->id, $b->fresh()->duplicada_de_id);

        $this->actingAs($this->usuario)->postJson(route('admin.conversiones.desmarcar-duplicada', $a))->assertOk();
        $this->assertNull($a->fresh()->duplicada_de_id);
        $this->assertNull($a->fresh()->duplicada_at);
        $this->actingAs($this->usuario)->postJson(route('admin.conversiones.desmarcar-duplicada', $a))->assertStatus(422);
    }

    public function test_marcar_a_mano_exige_dos_del_mismo_cliente_y_ninguna_ya_duplicada(): void
    {
        $otro = Cliente::factory()->create();
        $a = $this->conv();
        $b = $this->conv([], $otro);
        $c = $this->conv();
        $d = $this->conv(['duplicada_de_id' => $c->id, 'duplicada_at' => now()]);

        $this->actingAs($this->usuario)->postJson(route('admin.conversiones.lote.duplicadas'), ['conversion_ids' => [$a->id]])->assertStatus(422);
        $this->actingAs($this->usuario)->postJson(route('admin.conversiones.lote.duplicadas'), ['conversion_ids' => [$a->id, $b->id]])->assertStatus(422);
        $this->actingAs($this->usuario)->postJson(route('admin.conversiones.lote.duplicadas'), ['conversion_ids' => [$a->id, $d->id]])->assertStatus(422);

        $this->assertNull($a->fresh()->duplicada_de_id);
    }

    // --- Listas, embudo y exportes excluyen duplicadas --------------------------------------------

    public function test_la_lista_oculta_las_duplicadas_y_las_muestra_con_el_enlace(): void
    {
        $original = $this->conv(['gclid' => 'GCLID-ORIGINAL']);
        $this->conv(['gclid' => 'GCLID-REPETIDA', 'duplicada_de_id' => $original->id, 'duplicada_at' => now()]);

        $lista = $this->actingAs($this->usuario)->get(route('admin.conversiones.index'))->assertOk();
        $lista->assertSee('GCLID-ORIGINAL')->assertDontSee('GCLID-REPETIDA')->assertSee('Ver duplicadas (1)');

        $this->actingAs($this->usuario)->get(route('admin.conversiones.index', ['duplicadas' => 1]))
            ->assertOk()->assertSee('GCLID-REPETIDA')->assertDontSee('GCLID-ORIGINAL')->assertSee('Duplicada de #'.$original->id);
    }

    public function test_la_pantalla_marca_las_sin_identificador_como_no_exportables_y_resume(): void
    {
        $etapa = $this->etapa($this->cliente);
        $this->conv(['ads_embudo_etapa_id' => $etapa->id]);                  // lista
        $this->conv();                                                         // falta etapa
        $this->conv(['gclid' => null]);                                        // sin identificador (sigue visible)
        $this->conv(['gclid' => null, 'ads_embudo_etapa_id' => $etapa->id]);   // sin identificador, con etapa

        $html = $this->actingAs($this->usuario)->get(route('admin.conversiones.index'))->assertOk()->getContent();

        $this->assertStringContainsString('1 listas para Google Ads', $html);
        $this->assertStringContainsString('1 sin etapa', $html);
        $this->assertStringContainsString('2 sin identificador de clic (no exportables)', $html);
        $this->assertSame(2, substr_count($html, 'No exportable: sin identificador'));
    }

    public function test_el_embudo_y_la_lista_por_cliente_excluyen_duplicadas(): void
    {
        $this->etapa($this->cliente); // sin etapas el embudo solo muestra el aviso
        $original = $this->conv(['gclid' => 'GCLID-ORIGINAL']);
        $this->conv(['gclid' => 'GCLID-REPETIDA', 'duplicada_de_id' => $original->id, 'duplicada_at' => now()]);

        foreach (['admin.clientes.conversiones', 'admin.clientes.conversiones.embudo'] as $ruta) {
            $this->actingAs($this->usuario)->get(route($ruta, $this->cliente))
                ->assertOk()->assertSee('GCLID-ORIGINAL')->assertDontSee('GCLID-REPETIDA');
        }
    }

    public function test_el_exporte_global_excluye_las_duplicadas_aunque_se_pida_verlas(): void
    {
        $original = $this->conv(['gclid' => 'GCLID-ORIGINAL']);
        $this->conv(['gclid' => 'GCLID-REPETIDA', 'duplicada_de_id' => $original->id, 'duplicada_at' => now()]);

        $contenido = $this->actingAs($this->usuario)
            ->post(route('admin.conversiones.exportar-excel'), ['duplicadas' => 1])
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('GCLID-ORIGINAL', $contenido);
        $this->assertStringNotContainsString('GCLID-REPETIDA', $contenido);
    }

    // --- Exporte a Google Ads -----------------------------------------------------------------------

    public function test_el_exporte_a_google_ads_solo_lleva_clasificadas_con_identificador(): void
    {
        $etapa = $this->etapa($this->cliente);
        $buena = $this->conv(['gclid' => 'GCLID-BUENA', 'ads_embudo_etapa_id' => $etapa->id, 'valor' => 250]);
        $sinEtapa = $this->conv(['gclid' => 'GCLID-SIN-ETAPA']);
        $sinId = $this->conv(['gclid' => null, 'ads_embudo_etapa_id' => $etapa->id]);
        $duplicada = $this->conv(['gclid' => 'GCLID-DUP', 'ads_embudo_etapa_id' => $etapa->id, 'duplicada_de_id' => $buena->id, 'duplicada_at' => now()]);
        $conGbraid = $this->conv(['gclid' => null, 'gbraid' => 'GBRAID-OK', 'ads_embudo_etapa_id' => $etapa->id]);

        $respuesta = $this->actingAs($this->usuario)->post(route('admin.clientes.conversiones.exportar', $this->cliente))->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('GCLID-BUENA', $contenido);
        $this->assertStringContainsString('GBRAID-OK', $contenido);
        $this->assertStringNotContainsString('GCLID-SIN-ETAPA', $contenido);
        $this->assertStringNotContainsString('GCLID-DUP', $contenido);
        $this->assertCount(3, $this->csv($contenido), 'encabezado + 2 filas');

        $this->assertSame('2', $respuesta->headers->get('X-Exportadas'));
        $this->assertSame('1', $respuesta->headers->get('X-Omitidas-Sin-Etapa'));
        $this->assertSame('1', $respuesta->headers->get('X-Omitidas-Sin-Identificador'));
        $this->assertSame('1', $respuesta->headers->get('X-Omitidas-Duplicadas'));

        // Solo las exportadas cambian de estado; las omitidas siguen pendientes para poder corregirlas.
        $this->assertSame('exportada', $buena->fresh()->estado->value);
        $this->assertSame('exportada', $conGbraid->fresh()->estado->value);
        foreach ([$sinEtapa, $sinId, $duplicada] as $omitida) {
            $this->assertSame('pendiente', $omitida->fresh()->estado->value);
            $this->assertNull($omitida->fresh()->exportada_at);
        }
    }

    public function test_el_exporte_conserva_el_nombre_de_conversion_por_tipo(): void
    {
        $etapa = $this->etapa($this->cliente, 'Venta cerrada');
        $this->conv(['ads_embudo_etapa_id' => $etapa->id, 'tipo' => 'llamada']);

        $contenido = $this->actingAs($this->usuario)->post(route('admin.clientes.conversiones.exportar', $this->cliente))->streamedContent();

        $this->assertStringContainsString('Llamada', $contenido);
        $this->assertStringNotContainsString('Venta cerrada', $contenido);
    }

    public function test_el_exporte_sin_nada_exportable_devuelve_solo_encabezado_y_no_marca_nada(): void
    {
        $sinEtapa = $this->conv();

        $respuesta = $this->actingAs($this->usuario)->post(route('admin.clientes.conversiones.exportar', $this->cliente))->assertOk();

        $this->assertCount(1, $this->csv($respuesta->streamedContent()));
        $this->assertSame('0', $respuesta->headers->get('X-Exportadas'));
        $this->assertSame('pendiente', $sinEtapa->fresh()->estado->value);
    }

    public function test_la_pagina_por_cliente_dice_cuantas_se_pueden_exportar_y_por_que_no_las_demas(): void
    {
        $etapa = $this->etapa($this->cliente);
        $this->conv(['ads_embudo_etapa_id' => $etapa->id]);
        $this->conv();
        $this->conv(['gclid' => null]);

        $this->actingAs($this->usuario)->get(route('admin.clientes.conversiones', $this->cliente))
            ->assertOk()
            ->assertSee('1 pendientes listas para Google Ads')
            ->assertSee('1 sin etapa')
            ->assertSee('1 sin identificador de clic (no exportables)');
    }

    // --- Autorización ------------------------------------------------------------------------------------

    public function test_las_acciones_en_lote_exigen_sesion_y_permiso_del_modulo(): void
    {
        $a = $this->conv();
        $b = $this->conv();
        $etapa = $this->etapa($this->cliente);

        $this->post(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id], 'ads_embudo_etapa_id' => $etapa->id])->assertRedirect();
        $this->assertNull($a->fresh()->ads_embudo_etapa_id);

        config(['permisos.aplicar' => true]);
        $rol = Role::query()->create(['name' => 'operador', 'label' => 'Operador']);
        $rol->permissions()->attach(Permission::query()->firstOrCreate(['name' => 'clientes'], ['label' => 'clientes', 'module' => 'clientes'])->id);
        $sinPermiso = User::factory()->create(['role_id' => $rol->id, 'is_active' => true]);

        $this->actingAs($sinPermiso)->postJson(route('admin.conversiones.lote.etapa'), ['conversion_ids' => [$a->id], 'ads_embudo_etapa_id' => $etapa->id])->assertForbidden();
        $this->actingAs($sinPermiso)->postJson(route('admin.conversiones.lote.duplicadas'), ['conversion_ids' => [$a->id, $b->id]])->assertForbidden();
        $this->actingAs($sinPermiso)->postJson(route('admin.conversiones.desmarcar-duplicada', $a))->assertForbidden();

        $this->assertNull($a->fresh()->ads_embudo_etapa_id);
        $this->assertNull($b->fresh()->duplicada_de_id);
    }

    public function test_eliminar_un_cliente_con_duplicadas_borra_original_y_marcadas_sin_chocar_con_la_llave_propia(): void
    {
        $original = $this->conv();
        $this->conv(['duplicada_de_id' => $original->id, 'duplicada_at' => now()]);

        $this->cliente->delete();

        $this->assertSame(0, AdsConversion::count());
    }
}
