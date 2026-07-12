<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * keywords_ids stores an array of IDs referencing the existing `keywords`
 * table (banco de keywords) — deliberately not a JSON blob of raw keyword
 * text, so the relational keyword data (tipo, volumen, dificultad, ...)
 * stays the single source of truth and doesn't get duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seo_fase_estrategia');

        Schema::create('seo_fase_estrategia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->json('keywords_ids')->nullable();
            $table->text('analisis_competencia')->nullable();
            $table->text('plan_contenido')->nullable();
            $table->text('estrategia_link_building')->nullable();
            $table->unsignedInteger('meta_trafico_mensual')->nullable();
            $table->unsignedInteger('meta_top3')->nullable();
            $table->unsignedInteger('meta_top10')->nullable();
            $table->unsignedInteger('meta_leads_mensual')->nullable();
            $table->string('herramientas')->nullable();
            $table->text('cronograma')->nullable();
            $table->text('notas')->nullable();

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
