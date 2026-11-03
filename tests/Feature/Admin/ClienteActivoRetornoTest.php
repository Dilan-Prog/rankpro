<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use App\Support\ClienteActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteActivoRetornoTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_devuelve_null_y_limpia_la_sesion_si_el_cliente_ya_no_existe(): void
    {
        $cliente = Cliente::factory()->create();
        session([ClienteActivo::CLAVE => $cliente->id]);
        $this->assertSame($cliente->id, ClienteActivo::id());

        $cliente->delete();

        $this->assertNull(ClienteActivo::id());
        $this->assertNull(ClienteActivo::modelo());
        $this->assertNull(session(ClienteActivo::CLAVE));
    }

    public function test_cambiar_el_cliente_desde_una_pagina_de_contenido_vuelve_al_indice(): void
    {
        $a = Cliente::factory()->create();
        $b = Cliente::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.contenido.index').'/123')
            ->post(route('admin.cliente-activo'), ['cliente_id' => $b->id])
            ->assertRedirect(route('admin.contenido.index'));

        $this->actingAs($user)
            ->from(route('admin.contenido.index').'?carpeta=9')
            ->post(route('admin.cliente-activo'), ['cliente_id' => $a->id])
            ->assertRedirect(route('admin.contenido.index'));
    }

    public function test_cambiar_el_cliente_quita_cliente_id_y_cliente_de_la_url_de_retorno(): void
    {
        $b = Cliente::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.propuestas.index').'?cliente_id=5&buscar=x')
            ->post(route('admin.cliente-activo'), ['cliente_id' => $b->id])
            ->assertRedirect(route('admin.propuestas.index').'?buscar=x');

        $this->actingAs($user)
            ->from(route('admin.reportes.tablero.index').'?cliente=5')
            ->post(route('admin.cliente-activo'), ['cliente_id' => $b->id])
            ->assertRedirect(route('admin.reportes.tablero.index'));
    }
}
