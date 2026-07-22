<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza la columna keywords (JSON de strings) de ads_grupos: cada
 * palabra clave ahora es su propia fila con datos de Keyword Planner
 * (volumen, competencia, CPC), permitiendo la captura tipo hoja de cálculo
 * palabra por palabra dentro del modal de grupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads_grupos', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });

        Schema::create('ads_grupo_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_grupo_id')->constrained('ads_grupos')->onDelete('cascade');
            $table->string('keyword');
            $table->unsignedInteger('volumen_busqueda')->nullable();
            $table->enum('competencia', ['baja', 'media', 'alta'])->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_grupo_keywords');

        Schema::table('ads_grupos', function (Blueprint $table) {
            $table->json('keywords')->nullable();
        });
    }
};
