<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas personalizadas de la hoja de cálculo de keywords, definidas por
 * grupo de anuncios (no globales) — el usuario puede agregar los campos
 * que necesite además de volumen/competencia/CPC. El valor por fila se
 * guarda en ads_grupo_keywords.datos_personalizados como JSON keyed por
 * el id de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_keyword_columnas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_grupo_id')->constrained('ads_grupos')->onDelete('cascade');
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::table('ads_grupo_keywords', function (Blueprint $table) {
            $table->json('datos_personalizados')->nullable()->after('cpc');
        });
    }

    public function down(): void
    {
        Schema::table('ads_grupo_keywords', function (Blueprint $table) {
            $table->dropColumn('datos_personalizados');
        });

        Schema::dropIfExists('ads_keyword_columnas');
    }
};
