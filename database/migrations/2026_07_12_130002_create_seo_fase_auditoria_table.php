<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 (Auditoría) data already lives on seo_campanas (seo_score,
 * velocidad_mobile/desktop, sitemap_ok, robots_ok, errores_tecnicos,
 * trafico_organico_mensual) — this table only tracks the phase's
 * checklist/approval state, mirroring the proyecto_planeacion pattern
 * without duplicating those columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_fase_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_fase_auditoria');
    }
};
