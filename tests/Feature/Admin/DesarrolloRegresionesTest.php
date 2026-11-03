<?php

namespace Tests\Feature\Admin;

use App\Models\Bug;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regresiones de la auditoria QA del modulo Desarrollo. */
class DesarrolloRegresionesTest extends TestCase
{
    use RefreshDatabase;

    private function proyecto(array $overrides = []): Proyecto
    {
        return Proyecto::create(array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'nombre' => 'Proyecto',
            'tipo' => 'web_nueva',
            'fase_actual' => 'control',
            'porcentaje_avance' => 0,
            'presupuesto' => 1000,
            'anticipo' => 0,
            'pagos_recibidos' => 0,
            'estado' => 'activo',
        ], $overrides));
    }

    private function datosUpdate(Proyecto $p, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $p->cliente_id, 'nombre' => $p->nombre, 'tipo' => $p->tipo,
            'presupuesto' => 1000, 'pagos_recibidos' => 0, 'estado' => 'activo',
        ], $extra);
    }

    public function test_en_proceso_no_cuenta_pausados_ni_cancelados(): void
    {
        $this->proyecto(['estado' => 'activo']);
        $this->proyecto(['estado' => 'pausado']);
        $this->proyecto(['estado' => 'cancelado']);

        $this->assertSame(1, $this->actingAs(User::factory()->create())->get(route('admin.desarrollo.index'))->viewData('enProceso'));
    }

    public function test_un_proyecto_cancelado_no_tiene_dias_restantes(): void
    {
        $p = $this->proyecto(['fecha_entrega_estimada' => now()->subDays(5)]);

        $res = $this->actingAs(User::factory()->create())
            ->putJson(route('admin.desarrollo.update', $p), $this->datosUpdate($p, ['estado' => 'cancelado']));

        $res->assertOk()->assertJsonPath('dias_restantes', null);
    }

    public function test_la_entrega_estimada_no_puede_ser_anterior_al_inicio(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'X', 'tipo' => 'web_nueva', 'presupuesto' => 100,
            'fecha_inicio' => '2026-06-10', 'fecha_entrega_estimada' => '2026-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors('fecha_entrega_estimada');
    }

    public function test_anticipo_y_pagos_no_pueden_superar_el_presupuesto(): void
    {
        $p = $this->proyecto();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('admin.desarrollo.update', $p), $this->datosUpdate($p, ['pagos_recibidos' => 1500]))
            ->assertStatus(422)->assertJsonValidationErrors('pagos_recibidos');
        $this->actingAs($user)->putJson(route('admin.desarrollo.update', $p), $this->datosUpdate($p, ['anticipo' => 1500]))
            ->assertStatus(422)->assertJsonValidationErrors('anticipo');
        $this->actingAs($user)->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => $p->cliente_id, 'nombre' => 'X', 'tipo' => 'web_nueva', 'presupuesto' => 100, 'anticipo' => 200,
        ])->assertStatus(422)->assertJsonValidationErrors('anticipo');
    }

    public function test_el_bug_rellena_fecha_resolucion_al_resolverse_y_la_limpia_al_reabrirse(): void
    {
        $p = $this->proyecto();
        $user = User::factory()->create();
        $bug = $p->bugs()->create(['titulo' => 'Falla', 'prioridad' => 'media', 'estado' => 'abierto']);
        $datos = ['titulo' => 'Falla', 'prioridad' => 'media'];

        $this->actingAs($user)->putJson(route('admin.desarrollo.bugs.update', $bug), $datos + ['estado' => 'resuelto'])
            ->assertOk()->assertJsonPath('fecha_resolucion', now()->toDateString());

        $this->actingAs($user)->putJson(route('admin.desarrollo.bugs.update', $bug), $datos + ['estado' => 'en_progreso'])
            ->assertOk()->assertJsonPath('fecha_resolucion', null);
        $this->assertNull(Bug::find($bug->id)->fecha_resolucion);
    }

    public function test_entrega_estimada_sin_fecha_de_inicio_es_valida(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('admin.desarrollo.store'), [
            'cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'X', 'tipo' => 'web_nueva', 'presupuesto' => 100,
            'fecha_entrega_estimada' => '2026-06-01',
        ])->assertCreated();
    }
}
