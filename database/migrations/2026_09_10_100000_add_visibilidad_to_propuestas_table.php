<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué elementos del PDF se imprimen: un mapa plano {clave => bool} cuyas claves
 * salen de App\Support\Propuestas\Visibilidad.
 *
 * Va en columna aparte y no dentro de los cinco JSON de contenido a propósito:
 * así el contenido no cambia de forma, hay un solo punto de verdad para "qué se
 * imprime", y una clave ausente significa visible. Por eso no hay backfill: las
 * propuestas existentes nacen con null y salen idénticas a como salían.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propuestas', function (Blueprint $table) {
            $table->json('visibilidad')->nullable()->after('condiciones_proyeccion');
        });
    }

    public function down(): void
    {
        Schema::table('propuestas', function (Blueprint $table) {
            $table->dropColumn('visibilidad');
        });
    }
};
