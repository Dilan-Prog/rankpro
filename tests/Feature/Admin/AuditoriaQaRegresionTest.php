<?php

namespace Tests\Feature\Admin;

use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Contenido\TablaContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regresiones de la auditoría de QA (Contenido y Conversiones). */
class AuditoriaQaRegresionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_celda_de_seleccion_con_opcion_de_mas_de_80_caracteres_se_guarda(): void
    {
        $larga = str_repeat('a', 100);

        $tabla = TablaContenido::normalizar([
            'columnas' => [['id' => 'c1', 'nombre' => 'Estado', 'tipo' => 'seleccion', 'opciones' => [$larga]]],
            'filas' => [['id' => 'f1', 'celdas' => ['c1' => $larga]]],
        ]);

        $this->assertSame(str_repeat('a', 80), $tabla['columnas'][0]['opciones'][0]);
        $this->assertSame(str_repeat('a', 80), $tabla['filas'][0]['celdas']['c1']);
    }

    public function test_conversiones_expone_el_valor_crudo_para_la_edicion_en_linea(): void
    {
        $cliente = Cliente::factory()->create();
        AdsConversion::create(['cliente_id' => $cliente->id, 'visitor_id' => 'v1', 'tipo' => 'whatsapp', 'valor' => 1234.5]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.conversiones.index'))
            ->assertOk()
            ->assertSee('1,234.50', false)
            ->assertSee('data-valor="1234.50"', false);
    }
}
