<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These 4 columns moved into the phase tables (tecnologias/url_repositorio/
 * url_staging -> proyecto_organizacion, url_produccion -> proyecto_control).
 * Any pre-existing data was carried over to those tables before this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['tecnologias', 'url_repositorio', 'url_staging', 'url_produccion']);
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->json('tecnologias')->nullable();
            $table->string('url_repositorio')->nullable();
            $table->string('url_staging')->nullable();
            $table->string('url_produccion')->nullable();
        });
    }
};
