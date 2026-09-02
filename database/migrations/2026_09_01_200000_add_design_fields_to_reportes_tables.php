<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que exige el diseño definitivo del entregable:
 *
 * - La portada declara el sitio, las fuentes y la versión. `sitio_web` sustituye
 *   además al apaño de deducirlo de la campaña SEO enlazada.
 * - Los KPIs y la portada declaran contra qué periodo se compara.
 * - Cada sección puede llevar rótulo ("SECCIÓN 2", "ANEXO A") y un aviso de dato
 *   desactualizado, que el diseño trata como elemento de primera clase.
 * - `reporte_entregas.version` permite que cada generación conserve su fichero:
 *   hasta ahora todas escribían en la misma ruta y se sobrescribían.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->string('sitio_web')->nullable()->after('titulo');
            $table->json('fuentes')->nullable()->after('notas_alcance');
            $table->date('comparativa_inicio')->nullable()->after('periodo_fin');
            $table->date('comparativa_fin')->nullable()->after('comparativa_inicio');
            $table->string('version_etiqueta')->nullable()->after('numero');
        });

        Schema::table('reporte_secciones', function (Blueprint $table) {
            $table->string('rotulo')->nullable()->after('titulo');
            $table->json('aviso')->nullable()->after('contenido');
        });

        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('formato');
        });

        // Borrar el PDF desde el módulo de Archivos no debe llevarse por delante
        // el registro de que esa entrega existió: el histórico es el valor de la
        // tabla. La vista ya contempla el caso de una entrega sin archivo.
        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->dropForeign(['archivo_id']);
        });

        DB::statement('ALTER TABLE reporte_entregas MODIFY archivo_id BIGINT UNSIGNED NULL');

        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->foreign('archivo_id')->references('id')->on('archivos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->dropForeign(['archivo_id']);
        });

        DB::statement('DELETE FROM reporte_entregas WHERE archivo_id IS NULL');
        DB::statement('ALTER TABLE reporte_entregas MODIFY archivo_id BIGINT UNSIGNED NOT NULL');

        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->foreign('archivo_id')->references('id')->on('archivos')->onDelete('cascade');
            $table->dropColumn('version');
        });

        Schema::table('reporte_secciones', function (Blueprint $table) {
            $table->dropColumn(['rotulo', 'aviso']);
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['sitio_web', 'fuentes', 'comparativa_inicio', 'comparativa_fin', 'version_etiqueta']);
        });
    }
};
