<?php

namespace Tests\Feature\Admin;

use App\Models\CorreoPlantilla;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\GruposPermisos;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Protección contra bloqueos (último admin activo, rol admin) y las mejoras de
 * Roles/Usuarios/Correo que la acompañan: duplicar rol, matriz agrupada,
 * exportación CSV, KPI de archivadas y ficha de la plantilla.
 */
class ProteccionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function rol(string $name, int $permisos = 1): Role
    {
        $rol = Role::create(['name' => $name, 'label' => ucfirst($name)]);
        $ids = [];
        for ($i = 0; $i < $permisos; $i++) {
            $ids[] = Permission::create(['name' => "{$name}_p{$i}", 'label' => "P {$name} {$i}", 'module' => "{$name}_p{$i}"])->id;
        }
        $rol->permissions()->sync($ids);

        return $rol;
    }

    private function usuario(?Role $rol, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role_id' => $rol?->id, 'is_active' => true], $attrs));
    }

    private function payloadUsuario(User $u, array $cambios = []): array
    {
        return array_merge([
            'name' => $u->name,
            'email' => $u->email,
            'role_id' => $u->role_id,
            'is_active' => $u->is_active ? 1 : 0,
        ], $cambios);
    }

    // --- último admin: edición ----------------------------------------------

    public function test_no_se_puede_quitar_el_rol_admin_al_ultimo_admin_activo(): void
    {
        $admin = $this->rol('admin');
        $otro = $this->rol('editor');
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($otro));

        $this->putJson(route('admin.usuarios.update', $unico), $this->payloadUsuario($unico, ['role_id' => $otro->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');

        $this->assertSame($admin->id, $unico->fresh()->role_id);
    }

    public function test_no_se_puede_cambiar_al_ultimo_admin_a_un_rol_sin_permisos(): void
    {
        $admin = $this->rol('admin');
        $vacio = $this->rol('vacio', 0);
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($this->rol('editor')));

        $res = $this->putJson(route('admin.usuarios.update', $unico), $this->payloadUsuario($unico, ['role_id' => $vacio->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');

        $this->assertStringContainsString('sin permisos', $res->json('errors.role_id.0'));
    }

    public function test_no_se_puede_dejar_sin_rol_al_ultimo_admin(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.usuarios.update', $unico), $this->payloadUsuario($unico, ['role_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role_id');
    }

    public function test_no_se_puede_desactivar_al_ultimo_admin_desde_el_formulario(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.usuarios.update', $unico), $this->payloadUsuario($unico, ['is_active' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->assertTrue($unico->fresh()->is_active);
    }

    public function test_no_se_puede_desactivar_al_ultimo_admin_con_la_accion_rapida(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->postJson(route('admin.usuarios.deactivate', $unico))->assertStatus(422);

        $this->assertTrue($unico->fresh()->is_active);
    }

    public function test_un_admin_inactivo_no_cuenta_como_respaldo(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        $this->usuario($admin, ['is_active' => false]);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->postJson(route('admin.usuarios.deactivate', $unico))->assertStatus(422);
    }

    public function test_un_admin_puede_dejar_de_serlo_si_queda_otro_activo(): void
    {
        $admin = $this->rol('admin');
        $otro = $this->rol('editor');
        $a = $this->usuario($admin);
        $this->usuario($admin);
        $this->actingAs($this->usuario($otro));

        $this->putJson(route('admin.usuarios.update', $a), $this->payloadUsuario($a, ['role_id' => $otro->id]))
            ->assertOk();

        $this->assertSame($otro->id, $a->fresh()->role_id);
    }

    public function test_editar_otros_datos_del_ultimo_admin_sigue_permitido(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.usuarios.update', $unico), $this->payloadUsuario($unico, ['name' => 'Nombre nuevo']))
            ->assertOk();

        $this->assertSame('Nombre nuevo', $unico->fresh()->name);
    }

    // --- rol admin -----------------------------------------------------------

    public function test_no_se_puede_eliminar_el_rol_admin_aunque_no_tenga_usuarios(): void
    {
        $admin = $this->rol('admin');
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->deleteJson(route('admin.roles.destroy', $admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delete');

        $this->assertDatabaseHas('roles', ['id' => $admin->id]);
    }

    public function test_no_se_puede_dejar_sin_permisos_al_rol_admin(): void
    {
        $admin = $this->rol('admin', 2);
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.roles.update', $admin), ['name' => 'admin', 'label' => 'Admin', 'permissions' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions');

        $this->assertSame(2, $admin->permissions()->count());
    }

    public function test_no_se_puede_renombrar_el_rol_admin(): void
    {
        $admin = $this->rol('admin');
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.roles.update', $admin), [
            'name' => 'superadmin',
            'label' => 'Admin',
            'permissions' => $admin->permissions()->pluck('permissions.id')->all(),
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_el_rol_admin_se_puede_editar_si_conserva_permisos_e_identificador(): void
    {
        $admin = $this->rol('admin');
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->putJson(route('admin.roles.update', $admin), [
            'name' => 'admin',
            'label' => 'Administración',
            'permissions' => $admin->permissions()->pluck('permissions.id')->all(),
        ])->assertOk();

        $this->assertSame('Administración', $admin->fresh()->label);
    }

    // --- API -----------------------------------------------------------------

    public function test_la_api_tambien_protege_al_ultimo_admin_y_al_rol_admin(): void
    {
        $admin = $this->rol('admin');
        $unico = $this->usuario($admin);
        Sanctum::actingAs($this->usuario($this->rol('editor')), ['*']);

        $this->postJson("/api/v1/usuarios/{$unico->id}/desactivar")->assertStatus(422);
        $this->putJson("/api/v1/usuarios/{$unico->id}", $this->payloadUsuario($unico, ['is_active' => 0]))->assertStatus(422);
        $this->deleteJson("/api/v1/roles/{$admin->id}")->assertStatus(422);

        $this->assertTrue($unico->fresh()->is_active);
        $this->assertDatabaseHas('roles', ['id' => $admin->id]);
    }

    // --- duplicar rol --------------------------------------------------------

    public function test_duplicar_un_rol_copia_sus_permisos_con_nombre_sugerido(): void
    {
        $origen = $this->rol('editor_seo', 3);
        $this->actingAs($this->usuario($this->rol('editor')));

        $res = $this->postJson(route('admin.roles.duplicar', $origen))->assertCreated();

        $this->assertSame('Copia de Editor_seo', $res->json('label'));
        $this->assertSame('editor_seo_copia', $res->json('name'));
        $this->assertSame(0, $res->json('users_count'));
        $copia = Role::where('name', 'editor_seo_copia')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $origen->permissions()->pluck('permissions.id')->all(),
            $copia->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_duplicar_dos_veces_no_choca_con_el_nombre_unico(): void
    {
        $origen = $this->rol('editor_seo');
        $this->actingAs($this->usuario($this->rol('editor')));

        $this->postJson(route('admin.roles.duplicar', $origen))->assertCreated();
        $segunda = $this->postJson(route('admin.roles.duplicar', $origen))->assertCreated();

        $this->assertSame('editor_seo_copia_2', $segunda->json('name'));
    }

    // --- matriz agrupada y ficha --------------------------------------------

    public function test_la_matriz_lista_los_modulos_nuevos_agrupados(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->actingAs($this->usuario($this->rol('editor')));

        $res = $this->get(route('admin.roles.index'))->assertOk();

        foreach (['inicio', 'mi-espacio', 'calendario', 'identidad', 'audiencia', 'seo-plan', 'contenido', 'agenda', 'configuracion'] as $modulo) {
            $res->assertSee('value="'.Permission::where('name', $modulo)->value('id').'"', false);
            $this->assertNotSame(GruposPermisos::OTROS, GruposPermisos::tituloDe($modulo), "{$modulo} sin grupo");
        }
        $res->assertSee('Panel y trabajo diario');
        $res->assertSee('Administración');
        $this->assertSame(Permission::count(), collect($res->viewData('gruposPermisos'))->sum(fn ($g) => count($g['permisos'])));
    }

    public function test_un_modulo_desconocido_cae_en_otros_y_no_desaparece(): void
    {
        $perm = Permission::create(['name' => 'raro', 'label' => 'Raro', 'module' => 'raro']);

        $grupos = GruposPermisos::agrupar(collect([$perm]));

        $this->assertSame(GruposPermisos::OTROS, $grupos[0]['titulo']);
    }

    public function test_el_row_del_rol_incluye_sus_usuarios_para_la_ficha(): void
    {
        $rol = $this->rol('editor');
        $u = $this->usuario($rol, ['name' => 'Ana Prueba']);
        $this->actingAs($this->usuario($this->rol('otro')));

        $row = $this->get(route('admin.roles.index'))->viewData('roles')->firstWhere('id', $rol->id);

        $this->assertSame([['id' => $u->id, 'name' => 'Ana Prueba', 'email' => $u->email, 'is_active' => true]], $row['usuarios']);
    }

    // --- usuarios: panel de módulos y exportación ---------------------------

    public function test_usuarios_embebe_los_modulos_de_cada_rol_agrupados(): void
    {
        $this->seed(PermissionSeeder::class);
        $rol = Role::create(['name' => 'parcial', 'label' => 'Parcial']);
        $rol->permissions()->sync(Permission::whereIn('name', ['inicio', 'usuarios'])->pluck('id'));
        $this->actingAs($this->usuario($rol));

        $mapa = $this->get(route('admin.usuarios.index'))->assertOk()->viewData('permisosPorRol');

        $this->assertEquals(
            [
                ['titulo' => 'Panel y trabajo diario', 'modulos' => ['Inicio']],
                ['titulo' => 'Administración', 'modulos' => ['Usuarios']],
            ],
            $mapa[$rol->id]
        );
    }

    public function test_exportar_devuelve_csv_utf8_con_bom_y_escapa_formulas(): void
    {
        $rol = $this->rol('editor');
        $this->usuario($rol, ['name' => '=HYPERLINK("x")', 'email' => 'mal@example.com', 'area' => 'seo']);
        $this->usuario(null, ['name' => 'María Ñandú', 'email' => 'maria@example.com', 'is_active' => false]);
        $this->actingAs($this->usuario($rol));

        $res = $this->get(route('admin.usuarios.exportar'))->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nombre,Correo,Rol', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
        $this->assertStringContainsString('"María Ñandú",maria@example.com,"Sin rol"', $csv);
        $this->assertStringContainsString('Inactivo', $csv);
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
    }

    public function test_exportar_exige_autenticacion(): void
    {
        $this->get(route('admin.usuarios.exportar'))->assertRedirect('/login');
    }

    // --- correo: KPI de archivadas y ficha ----------------------------------

    public function test_plantillas_muestra_el_kpi_de_archivadas(): void
    {
        CorreoPlantilla::factory()->create(['estado' => 'activa']);
        CorreoPlantilla::factory()->count(2)->create(['estado' => 'archivada']);
        $this->actingAs($this->usuario($this->rol('editor')));

        $res = $this->get(route('admin.correo.plantillas.index'))->assertOk();

        $this->assertSame(2, $res->viewData('kpis')['archivadas']);
        $res->assertSee('Archivadas');
    }

    public function test_el_editor_recibe_peso_y_variables_de_la_plantilla(): void
    {
        $plantilla = CorreoPlantilla::factory()->create([
            'asunto' => 'Hola {{contacto}}',
            'bloques' => [['tipo' => 'text', 'texto' => 'Reporte de {{mes}} para {{cliente}}']],
        ]);
        $this->actingAs($this->usuario($this->rol('editor')));

        $ficha = $this->get(route('admin.correo.plantillas.show', $plantilla))->assertOk()->viewData('ficha');

        $this->assertGreaterThan(0, $ficha['peso_bytes']);
        $this->assertEqualsCanonicalizing(['contacto', 'mes', 'cliente'], $ficha['variables']);
    }
}
