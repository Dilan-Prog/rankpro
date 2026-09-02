<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Datos" and "Entregable" are new values alongside the 5 existing ones —
 * purely additive (no existing values are removed), so this is safe as a
 * single ALTER, same pattern as add_cerrada_to_seo_campanas_fase_enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE archivos MODIFY tipo ENUM('contrato', 'propuesta', 'diseno', 'reporte', 'otro', 'datos', 'entregable') NOT NULL DEFAULT 'otro'");
    }

    public function down(): void
    {
        DB::statement("UPDATE archivos SET tipo = 'otro' WHERE tipo IN ('datos', 'entregable')");
        DB::statement("ALTER TABLE archivos MODIFY tipo ENUM('contrato', 'propuesta', 'diseno', 'reporte', 'otro') NOT NULL DEFAULT 'otro'");
    }
};
