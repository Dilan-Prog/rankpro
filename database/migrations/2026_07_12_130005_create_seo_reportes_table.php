<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per cycle (not 1:1 like the other phase tables) so past monthly
 * reports stay in history instead of being overwritten when the campaign
 * loops back into Ejecución for the next cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo');
            $table->text('resultados_vs_metas')->nullable();
            $table->unsignedInteger('trafico_organico_final')->nullable();
            $table->unsignedInteger('posiciones_ganadas')->nullable();
            $table->decimal('roas_organico', 8, 2)->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_reportes');
    }
};
