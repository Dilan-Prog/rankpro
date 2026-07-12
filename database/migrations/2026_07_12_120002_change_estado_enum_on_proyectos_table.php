<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blueprint::change() needs doctrine/dbal (not installed here), so this
 * modifies the enum column directly. `estado` used to track the SDLC stage
 * (briefing/diseño/frontend/.../mantenimiento) — that job now belongs to
 * the new `fase_actual` column, so `estado` is repurposed as a simple
 * project status (activo/pausado/cancelado/cerrado). Existing rows are
 * remapped to 'activo' before the column type changes, since none of the
 * old values are valid in the new enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Widen first so old values stay valid while rows are remapped, then narrow to the final set.
        DB::statement("ALTER TABLE proyectos MODIFY estado ENUM('briefing', 'disenio', 'frontend', 'backend', 'qa', 'lanzamiento', 'mantenimiento', 'activo', 'pausado', 'cancelado', 'cerrado') NOT NULL DEFAULT 'briefing'");
        DB::statement("UPDATE proyectos SET estado = 'activo'");
        DB::statement("ALTER TABLE proyectos MODIFY estado ENUM('activo', 'pausado', 'cancelado', 'cerrado') NOT NULL DEFAULT 'activo'");
    }

    public function down(): void
    {
        DB::statement("UPDATE proyectos SET estado = 'briefing'");
        DB::statement("ALTER TABLE proyectos MODIFY estado ENUM('briefing', 'disenio', 'frontend', 'backend', 'qa', 'lanzamiento', 'mantenimiento') NOT NULL DEFAULT 'briefing'");
    }
};
