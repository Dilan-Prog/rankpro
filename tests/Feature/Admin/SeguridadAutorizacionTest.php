<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Permission;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\Tarea;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresiones de la auditoría de seguridad: IDOR de tareas personales, sesión de usuarios
 * desactivados, permisos de la API y autoescalada por usuarios/roles.
 */
class SeguridadAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    private function rol(string $nombre, array $modulos = []): Role
    {
        $rol = Role::query()->firstOrCreate(['name' => $nombre], ['label' => ucfirst($nombre)]);
        foreach ($modulos as $modulo) {
            $permiso = Permission::query()->firstOrCreate(['name' => $modulo], ['label' => $modulo, 'module' => $modulo]);
            $rol->permissions()->syncWithoutDetaching([$permiso->id]);
        }

        return $rol;
    }

    private function usuario(Role $rol, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role_id' => $rol->id, 'is_active' => true], $extra));
    }

    private function tareaPersonal(User $dueno): Tarea
    {
        return Tarea::create([
            'proyecto_id' => null, 'user_id' => $dueno->id, 'titulo' => 'Privada',
            'prioridad' => 'media', 'estado' => 'pendiente',
        ]);
    }

    private function proyecto(): Proyecto
    {
        return Proyecto::create([
            'cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'P', 'tipo' => 'web_nueva',
            'fase_actual' => 'planeacion', 'porcentaje_avance' => 0, 'presupuesto' => 1, 'anticipo' => 0,
            'pagos_recibidos' => 0, 'estado' => 'activo',
        ]);
    }

    private function payloadTarea(): array
    {
        return ['titulo' => 'Hackeada', 'prioridad' => 'alta', 'estado' => 'completada'];
    }

    // --- 1) IDOR de tareas personales ------------------------------------

    public function test_las_rutas_de_desarrollo_no_tocan_tareas_personales(): void
    {
        $atacante = User::factory()->create();
        $tarea = $this->tareaPersonal(User::factory()->create());

        $this->actingAs($atacante)->putJson("/admin/desarrollo/tareas/{$tarea->id}", $this->payloadTarea())->assertNotFound();
        $this->actingAs($atacante)->deleteJson("/admin/desarrollo/tareas/{$tarea->id}")->assertNotFound();

        $this->assertSame('Privada', $tarea->fresh()->titulo);
    }

    public function test_la_api_de_tareas_no_toca_tareas_personales(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]), ['*']);
        $tarea = $this->tareaPersonal(User::factory()->create());

        $this->putJson("/api/v1/desarrollo/tareas/{$tarea->id}", $this->payloadTarea())->assertNotFound();
        $this->postJson("/api/v1/desarrollo/tareas/{$tarea->id}/completar")->assertNotFound();
        $this->deleteJson("/api/v1/desarrollo/tareas/{$tarea->id}")->assertNotFound();

        $this->assertSame('pendiente', $tarea->fresh()->estado->value);
    }

    public function test_una_tarea_con_proyecto_sigue_editable(): void
    {
        $tarea = $this->proyecto()->tareas()->create(['titulo' => 'Pública', 'prioridad' => 'media', 'estado' => 'pendiente']);

        $this->actingAs(User::factory()->create())
            ->putJson("/admin/desarrollo/tareas/{$tarea->id}", $this->payloadTarea())->assertOk();
    }

    public function test_tareas_personales_no_disparan_webhooks_pero_las_de_proyecto_si(): void
    {
        Webhook::factory()->create(['eventos' => ['*']]);

        $personal = $this->tareaPersonal(User::factory()->create());
        $personal->update(['estado' => 'completada']);
        $this->assertSame(0, WebhookEntrega::whereIn('evento', ['tarea.creada', 'tarea.completada'])->count());

        $this->proyecto()->tareas()->create(['titulo' => 'Con proyecto', 'prioridad' => 'media', 'estado' => 'pendiente']);
        $this->assertSame(1, WebhookEntrega::where('evento', 'tarea.creada')->count());
    }

    // --- 2) Usuario desactivado conserva la sesión -----------------------

    public function test_un_usuario_desactivado_es_expulsado_en_la_siguiente_peticion(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);

        // Login real (no actingAs): la sesión es lo que sobrevivía a la desactivación.
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);
        $this->get('/admin/dashboard')->assertOk();

        User::whereKey($usuario->id)->update(['is_active' => false]);
        $this->app['auth']->forgetGuards(); // nueva petición: el guard vuelve a leer al usuario de la BD

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_la_expulsion_tambien_cubre_peticiones_json(): void
    {
        $usuario = User::factory()->create(['is_active' => false]);

        $this->actingAs($usuario)->getJson('/admin/dashboard')->assertStatus(401);
        $this->assertGuest();
    }

    public function test_el_cookie_de_recordarme_de_un_desactivado_no_vale(): void
    {
        $usuario = User::factory()->create(['is_active' => true, 'remember_token' => 'tokenrecordar']);
        $cookie = auth()->guard('web')->getRecallerName();
        $valor = $usuario->id.'|tokenrecordar|'.$usuario->password;

        User::whereKey($usuario->id)->update(['is_active' => false]);

        $this->withUnencryptedCookie($cookie, $valor)->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // --- 3) /admin/integraciones sin permiso ------------------------------

    public function test_integraciones_exige_su_permiso(): void
    {
        config(['permisos.aplicar' => true]);

        $this->actingAs($this->usuario($this->rol('sin_integraciones', ['dashboard'])))
            ->get('/admin/integraciones')->assertForbidden();
        $this->actingAs($this->usuario($this->rol('con_integraciones', ['integraciones'])))
            ->get('/admin/integraciones')->assertOk();
    }

    public function test_todas_las_rutas_admin_salvo_las_legitimas_llevan_permiso(): void
    {
        $sinPermiso = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))
            ->reject(fn ($r) => in_array($r->uri(), ['admin', 'admin/cliente-activo'], true))
            ->filter(fn ($r) => ! collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && (str_contains($m, 'VerificarPermisoModulo') || str_starts_with($m, 'permiso:'))))
            ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())
            ->values()->all();

        $this->assertSame([], $sinPermiso);
    }

    // --- 4) La API respeta los permisos del dueño del token ---------------

    public function test_no_se_puede_crear_un_token_con_habilidades_que_el_rol_no_tiene(): void
    {
        config(['permisos.aplicar' => true]);
        $this->actingAs($this->usuario($this->rol('integrador', ['integraciones', 'clientes'])));

        $this->postJson('/admin/integraciones/api/tokens', ['nombre' => 'x', 'habilidades' => ['usuarios:escribir']])->assertStatus(422);
        $this->postJson('/admin/integraciones/api/tokens', ['nombre' => 'x', 'habilidades' => ['*']])->assertStatus(422);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->postJson('/admin/integraciones/api/tokens', ['nombre' => 'x', 'habilidades' => ['clientes:escribir']])->assertCreated();
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_un_admin_si_puede_crear_un_token_comodin(): void
    {
        config(['permisos.aplicar' => true]);

        $this->actingAs($this->usuario($this->rol('admin')))
            ->postJson('/admin/integraciones/api/tokens', ['nombre' => 'x', 'habilidades' => ['*']])->assertCreated();
    }

    public function test_un_token_con_habilidad_de_mas_no_funciona_si_el_dueno_no_tiene_el_modulo(): void
    {
        config(['permisos.aplicar' => true]);
        $dueno = $this->usuario($this->rol('integrador', ['integraciones']));
        Sanctum::actingAs($dueno, ['usuarios:escribir']);

        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->postJson('/api/v1/usuarios', ['name' => 'N', 'email' => 'n@example.com'])->assertForbidden();
    }

    public function test_un_token_funciona_cuando_el_dueno_tiene_el_modulo(): void
    {
        config(['permisos.aplicar' => true]);
        Sanctum::actingAs($this->usuario($this->rol('gestor', ['usuarios'])), ['usuarios:leer']);

        $this->getJson('/api/v1/usuarios')->assertOk();
    }

    // --- 5) Autoescalada ---------------------------------------------------

    public function test_un_no_admin_con_usuarios_no_puede_asignarse_el_rol_admin(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->rol('admin');
        $gestor = $this->usuario($this->rol('gestor', ['usuarios']));

        $this->actingAs($gestor)->putJson("/admin/usuarios/{$gestor->id}", [
            'name' => $gestor->name, 'email' => $gestor->email, 'is_active' => true, 'role_id' => $admin->id,
        ])->assertStatus(422)->assertJsonValidationErrors('role_id');
        $this->assertNotSame($admin->id, $gestor->fresh()->role_id);

        $this->postJson('/admin/usuarios', [
            'name' => 'Otro', 'email' => 'otro@example.com', 'role_id' => $admin->id,
            'password' => 'Clave-muy-segura-123', 'password_confirmation' => 'Clave-muy-segura-123',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'otro@example.com']);
    }

    public function test_un_admin_si_puede_asignar_el_rol_admin(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->rol('admin');
        $otro = $this->usuario($this->rol('operador'));

        $this->actingAs($this->usuario($admin))->putJson("/admin/usuarios/{$otro->id}", [
            'name' => $otro->name, 'email' => $otro->email, 'is_active' => true, 'role_id' => $admin->id,
        ])->assertOk();
    }

    public function test_un_no_admin_no_puede_modificar_ni_desactivar_a_un_admin(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->usuario($this->rol('admin'));
        $gestor = $this->usuario($this->rol('gestor', ['usuarios']));

        $this->actingAs($gestor)->putJson("/admin/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => 'tomado@example.com', 'is_active' => true, 'role_id' => $admin->role_id,
        ])->assertForbidden();
        $this->postJson("/admin/usuarios/{$admin->id}/desactivar")->assertForbidden();

        $this->assertNotSame('tomado@example.com', $admin->fresh()->email);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_un_no_admin_no_puede_darse_permisos_en_un_rol(): void
    {
        config(['permisos.aplicar' => true]);
        $rolGestor = $this->rol('gestor', ['roles']);
        $gestor = $this->usuario($rolGestor);
        $permisoFinanzas = Permission::create(['name' => 'finanzas', 'label' => 'finanzas', 'module' => 'finanzas']);
        $permisoRoles = Permission::where('name', 'roles')->first();

        $this->actingAs($gestor)->putJson("/admin/roles/{$rolGestor->id}", [
            'name' => 'gestor', 'label' => 'Gestor', 'permissions' => [$permisoRoles->id, $permisoFinanzas->id],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions');
        $this->assertFalse($rolGestor->fresh()->permissions->contains('name', 'finanzas'));

        // Conservar lo que ya tiene sí se puede.
        $this->putJson("/admin/roles/{$rolGestor->id}", [
            'name' => 'gestor', 'label' => 'Gestor 2', 'permissions' => [$permisoRoles->id],
        ])->assertOk();

        // Un rol nuevo tampoco puede nacer con permisos ajenos.
        $this->postJson('/admin/roles', ['name' => 'nuevo', 'label' => 'Nuevo', 'permissions' => [$permisoFinanzas->id]])->assertStatus(422);
    }

    public function test_un_no_admin_no_puede_duplicar_el_rol_admin(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->rol('admin', ['finanzas']);
        $gestor = $this->usuario($this->rol('gestor', ['roles']));

        $this->actingAs($gestor)->postJson("/admin/roles/{$admin->id}/duplicar")->assertStatus(422);
    }

    public function test_la_api_no_cambia_la_contrasena_de_un_admin_si_quien_llama_no_lo_es(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->usuario($this->rol('admin'));
        $hashAntes = $admin->password;
        Sanctum::actingAs($this->usuario($this->rol('gestor', ['usuarios'])), ['usuarios:escribir']);

        $this->postJson("/api/v1/usuarios/{$admin->id}/password", ['password' => 'Otra-clave-segura-456'])->assertForbidden();
        $this->assertSame($hashAntes, $admin->fresh()->password);
    }

    public function test_un_admin_si_cambia_la_contrasena_por_la_api(): void
    {
        config(['permisos.aplicar' => true]);
        $rolAdmin = $this->rol('admin');
        $objetivo = $this->usuario($rolAdmin);
        Sanctum::actingAs($this->usuario($rolAdmin), ['usuarios:escribir']);

        $this->postJson("/api/v1/usuarios/{$objetivo->id}/password", ['password' => 'Otra-clave-segura-456'])->assertOk();
    }

    public function test_la_api_no_asigna_rol_admin_a_un_no_admin(): void
    {
        config(['permisos.aplicar' => true]);
        $admin = $this->rol('admin');
        $gestor = $this->usuario($this->rol('gestor', ['usuarios']));
        Sanctum::actingAs($gestor, ['usuarios:escribir']);

        $this->putJson("/api/v1/usuarios/{$gestor->id}", ['name' => $gestor->name, 'email' => $gestor->email, 'role_id' => $admin->id])
            ->assertStatus(422);
        $this->assertNotSame($admin->id, $gestor->fresh()->role_id);
    }

    // --- 8) N+1 en el índice de usuarios ------------------------------------

    public function test_el_indice_de_usuarios_no_hace_una_consulta_de_servicios_por_usuario(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Cliente Uno']);
        foreach (User::factory()->count(5)->create() as $u) {
            Servicio::factory()->create(['cliente_id' => $cliente->id, 'responsable_id' => $u->id]);
        }
        $actor = User::factory()->create();

        DB::enableQueryLog();
        $respuesta = $this->actingAs($actor)->get('/admin/usuarios')->assertOk();
        $consultasServicios = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `servicios`'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $consultasServicios);
        $respuesta->assertSee('Cliente Uno');
    }
}
