<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_fase_estrategia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->text('analisis_competencia')->nullable();
            $table->text('plan_contenido')->nullable();
            $table->text('link_building_strategy')->nullable();
            $table->unsignedInteger('meta_trafico_mensual')->nullable();
            $table->unsignedInteger('meta_posiciones_top10')->nullable();
            $table->unsignedInteger('meta_leads_mensual')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_fase_estrategia');
    }
};
