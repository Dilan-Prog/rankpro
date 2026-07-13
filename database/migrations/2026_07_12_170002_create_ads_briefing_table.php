<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cycle-scoped (one row per ciclo), mirroring seo_fase_auditoria, so "Nuevo Ciclo" archives full history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_briefing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->text('publico_objetivo')->nullable();
            $table->string('rango_edad')->nullable();
            $table->string('genero')->nullable();
            $table->string('ubicacion_geografica')->nullable();
            $table->text('intereses')->nullable();
            $table->text('propuesta_valor')->nullable();
            $table->text('analisis_competencia')->nullable();
            $table->string('producto_servicio')->nullable();
            $table->string('url_destino')->nullable();
            $table->date('fecha_inicio_estimada')->nullable();
            $table->text('notas')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_briefing');
    }
};
