<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_metricas_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->unsignedBigInteger('trafico_organico')->default(0);
            $table->unsignedInteger('keywords_top3')->default(0);
            $table->unsignedInteger('keywords_top10')->default(0);
            $table->unsignedInteger('keywords_top100')->default(0);
            $table->unsignedInteger('backlinks_total')->default(0);
            $table->unsignedInteger('errores_resueltos')->default(0);
            $table->unsignedInteger('errores_pendientes')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['seo_campana_id', 'mes', 'anio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_metricas_mensuales');
    }
};
