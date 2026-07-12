<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Cerrada" is a new terminal value alongside the 4 existing phases —
 * purely additive (no existing values are removed), so this is safe as a
 * single ALTER unlike the narrowing case in the Desarrollo module.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE seo_campanas MODIFY fase_actual ENUM('auditoria', 'estrategia', 'ejecucion', 'reporte', 'cerrada') NOT NULL DEFAULT 'auditoria'");
    }

    public function down(): void
    {
        DB::statement("UPDATE seo_campanas SET fase_actual = 'reporte' WHERE fase_actual = 'cerrada'");
        DB::statement("ALTER TABLE seo_campanas MODIFY fase_actual ENUM('auditoria', 'estrategia', 'ejecucion', 'reporte') NOT NULL DEFAULT 'auditoria'");
    }
};
