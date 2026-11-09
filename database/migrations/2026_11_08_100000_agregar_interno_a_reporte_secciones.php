<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `interno` separa "solo para el equipo" de "para el cliente": una sección
     * interna se ve en el editor pero el Armador la excluye de todo entregable.
     * Default false a propósito: un default true ocultaría contenido ya creado.
     */
    public function up(): void
    {
        Schema::table('reporte_secciones', function (Blueprint $table) {
            $table->boolean('interno')->default(false)->after('visible');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_secciones', function (Blueprint $table) {
            $table->dropColumn('interno');
        });
    }
};
