<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `nombre` guarda a la persona de contacto y `empresa` el nombre comercial: el accessor es la
 * única regla que decide cuál se muestra en el panel.
 */
class ClienteNombreComercialTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_empresa_llena_el_nombre_comercial_es_la_empresa(): void
    {
        $cliente = new Cliente(['nombre' => 'Arquitecta Dunia', 'empresa' => 'Hotel Fratelli']);

        $this->assertSame('Hotel Fratelli', $cliente->nombre_comercial);
    }

    public function test_la_empresa_se_recorta_de_espacios(): void
    {
        $cliente = new Cliente(['nombre' => 'Ana', 'empresa' => '  Hotel Colonial  ']);

        $this->assertSame('Hotel Colonial', $cliente->nombre_comercial);
    }

    public function test_con_empresa_vacia_cae_al_nombre(): void
    {
        $this->assertSame('Ana Guerrero', (new Cliente(['nombre' => 'Ana Guerrero', 'empresa' => '']))->nombre_comercial);
    }

    public function test_con_empresa_de_solo_espacios_cae_al_nombre(): void
    {
        $this->assertSame('Ana Guerrero', (new Cliente(['nombre' => 'Ana Guerrero', 'empresa' => "  \t "]))->nombre_comercial);
    }

    public function test_con_empresa_nula_cae_al_nombre(): void
    {
        $this->assertSame('Ana Guerrero', (new Cliente(['nombre' => 'Ana Guerrero', 'empresa' => null]))->nombre_comercial);
    }

    public function test_el_accessor_no_se_cuela_en_la_serializacion_por_defecto(): void
    {
        $cliente = new Cliente(['nombre' => 'Ana', 'empresa' => 'Hotel']);

        $this->assertArrayNotHasKey('nombre_comercial', $cliente->toArray());
    }

    public function test_el_scope_ordena_por_nombre_comercial_y_no_por_nombre(): void
    {
        // Por `nombre` el orden sería Zoe, Alberto, Beto; por nombre comercial: Alfa, Beta, Zoe.
        Cliente::factory()->create(['nombre' => 'Alberto', 'empresa' => 'Zeta SA']);
        Cliente::factory()->create(['nombre' => 'Zoe', 'empresa' => '   ']);
        Cliente::factory()->create(['nombre' => 'Beto', 'empresa' => 'beta Hotel']);
        Cliente::factory()->create(['nombre' => 'Carla', 'empresa' => null]);

        $this->assertSame(
            ['beta Hotel', 'Carla', 'Zeta SA', 'Zoe'],
            Cliente::ordenadoPorNombreComercial()->get()->pluck('nombre_comercial')->all(),
        );
        $this->assertSame(
            ['Zoe', 'Zeta SA', 'Carla', 'beta Hotel'],
            Cliente::ordenadoPorNombreComercial('desc')->get()->pluck('nombre_comercial')->all(),
        );
    }
}
