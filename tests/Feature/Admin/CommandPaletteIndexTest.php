<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CommandPaletteIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La paleta Ctrl+K lleva clientes y keywords en el HTML de cada página: solo deben
 * viajar para quien tiene el permiso de ese módulo.
 */
class CommandPaletteIndexTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $modulos, string $rol): User
    {
        config(['permisos.aplicar' => true]);

        $role = Role::query()->firstOrCreate(['name' => $rol], ['label' => 'Rol']);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $role->permissions()->syncWithoutDetaching([$permiso->id]);
        }

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_clientes_y_keywords_solo_viajan_con_su_permiso(): void
    {
        $cliente = Cliente::factory()->create(['empresa' => 'Empresa Secreta']);
        Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'palabra secreta', 'tipo' => 'principal', 'estado' => 'en_uso']);

        $this->actingAs($this->usuarioCon(['inicio'], 'sin-crm'));
        $indice = CommandPaletteIndex::build();
        $this->assertSame([], $indice['clientes']);
        $this->assertSame([], $indice['keywords']);

        $this->actingAs($this->usuarioCon(['inicio', 'clientes'], 'solo-crm'));
        $indice = CommandPaletteIndex::build();
        $this->assertSame('Empresa Secreta', $indice['clientes'][0]['label']);
        $this->assertSame([], $indice['keywords']);

        $this->actingAs($this->usuarioCon(['inicio', 'clientes', 'keywords'], 'crm-keywords'));
        $indice = CommandPaletteIndex::build();
        $this->assertSame('palabra secreta', $indice['keywords'][0]['label']);
    }

    public function test_la_pagina_no_incrusta_clientes_para_un_rol_sin_permiso(): void
    {
        Cliente::factory()->create(['empresa' => 'Empresa Secreta']);

        $this->actingAs($this->usuarioCon(['inicio'], 'solo-inicio'))
            ->get(route('admin.inicio'))
            ->assertOk()
            ->assertDontSee('Empresa Secreta');
    }
}
