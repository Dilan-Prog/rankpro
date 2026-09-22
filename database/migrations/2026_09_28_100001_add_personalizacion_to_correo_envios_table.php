<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un envío puede personalizar el cuerpo del correo (bloques, marca, HTML
 * propio) sin tocar la plantilla original: las tres columnas son nulas por
 * defecto ("usa la plantilla tal cual"); en cuanto una tiene contenido, el
 * envío la usa en vez de la plantilla (ver CorreoEnvio::contenidoEfectivo()).
 * Misma forma que correo_plantillas.{bloques,marca,html_personalizado}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correo_envios', function (Blueprint $table) {
            $table->json('bloques')->nullable()->after('variables');
            $table->json('marca')->nullable()->after('bloques');
            $table->longText('html_personalizado')->nullable()->after('marca');
        });
    }

    public function down(): void
    {
        Schema::table('correo_envios', function (Blueprint $table) {
            $table->dropColumn(['bloques', 'marca', 'html_personalizado']);
        });
    }
};
