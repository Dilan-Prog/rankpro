<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resets each cycle (checklist/aprobado cleared back to false) when
 * "Siguiente Ciclo" is triggered from the Reporte phase — this is the
 * recurring operational phase, unlike Auditoría/Estrategia which are
 * approved once. Posiciones/backlinks stay in their own tables, scoped
 * by seo_campana_id, and are managed from this phase's panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_fase_ejecucion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->boolean('on_page_completado')->default(false);
            $table->boolean('off_page_completado')->default(false);
            $table->boolean('tecnico_completado')->default(false);
            $table->boolean('contenido_completado')->default(false);
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_fase_ejecucion');
    }
};
