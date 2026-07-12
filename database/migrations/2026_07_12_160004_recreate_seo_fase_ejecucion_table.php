<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seo_fase_ejecucion');

        Schema::create('seo_fase_ejecucion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->unsignedTinyInteger('porcentaje_avance')->default(0);

            // On-Page
            $table->unsignedInteger('paginas_optimizadas')->default(0);
            $table->boolean('titles_meta_ok')->default(false);
            $table->boolean('headings_ok')->default(false);
            $table->boolean('imagenes_ok')->default(false);
            $table->boolean('links_internos_ok')->default(false);

            // Off-Page / Link Building
            $table->unsignedInteger('backlinks_mes')->default(0);

            // Técnico
            $table->boolean('errores_404_ok')->default(false);
            $table->boolean('redirecciones_ok')->default(false);
            $table->boolean('schema_ok')->default(false);
            $table->boolean('velocidad_ok')->default(false);

            // Contenido
            $table->unsignedInteger('articulos_publicados')->default(0);

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
