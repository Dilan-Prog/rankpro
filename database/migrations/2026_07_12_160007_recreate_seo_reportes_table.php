<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seo_reportes');

        Schema::create('seo_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo');

            $table->unsignedInteger('trafico_inicio')->nullable();
            $table->unsignedInteger('trafico_actual')->nullable();
            $table->unsignedInteger('keywords_top3')->nullable();
            $table->unsignedInteger('keywords_top10')->nullable();
            $table->unsignedInteger('keywords_top100')->nullable();
            $table->unsignedInteger('backlinks_total')->nullable();
            $table->unsignedInteger('articulos_total')->nullable();
            $table->unsignedInteger('errores_resueltos')->nullable();
            $table->unsignedInteger('errores_pendientes')->nullable();
            $table->text('conclusiones')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->unsignedTinyInteger('satisfaccion_cliente')->nullable();
            $table->boolean('continua_campana')->nullable();
            $table->text('notas_cierre')->nullable();

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
