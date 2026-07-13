<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cycle-scoped like ads_briefing — the recurring operational phase, resets each "Nuevo Ciclo". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_lanzamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->date('fecha_lanzamiento')->nullable();
            $table->unsignedTinyInteger('porcentaje_avance')->default(0);

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_lanzamiento');
    }
};
