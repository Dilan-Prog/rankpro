<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per cycle (like seo_reportes) so past period reports stay in history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo');

            $table->decimal('inversion_total', 12, 2)->nullable();
            $table->unsignedBigInteger('impresiones_total')->nullable();
            $table->unsignedInteger('clics_total')->nullable();
            $table->decimal('ctr_promedio', 6, 3)->nullable();
            $table->unsignedInteger('conversiones_total')->nullable();
            $table->decimal('roas_promedio', 6, 2)->nullable();
            $table->decimal('cpl_promedio', 8, 2)->nullable();
            $table->decimal('cpa_promedio', 8, 2)->nullable();
            $table->string('mejor_anuncio_ctr')->nullable();
            $table->string('mejor_anuncio_conversiones')->nullable();
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
        Schema::dropIfExists('ads_reporte');
    }
};
