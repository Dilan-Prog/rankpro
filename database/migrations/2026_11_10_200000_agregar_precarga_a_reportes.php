<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Generar reporte del mes": el reporte recuerda si nació de la precarga mensual
 * (`origen`) y cada sección precargada guarda de qué fuente salió y la huella de
 * lo que se sembró (`precarga`), que es lo que permite "Actualizar datos" sin pisar
 * lo editado a mano. También se amplían dos enums: el de entregas con la versión para
 * cliente (un entregable aparte con su propia serie de versiones) y el tipo de sección
 * con `resumen_cliente`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            // 'mes' = precargado desde lo capturado; 'personalizado' = periodo libre sin precarga.
            $table->string('origen', 20)->default('personalizado')->after('area');
        });

        Schema::table('reporte_secciones', function (Blueprint $table) {
            // {clave, huella|partes, actualizado_en}: null en secciones manuales.
            $table->json('precarga')->nullable()->after('contenido');
        });

        DB::statement("ALTER TABLE reporte_entregas MODIFY formato ENUM('pdf','xlsx','pdf_cliente') NOT NULL");
        DB::statement("ALTER TABLE reporte_secciones MODIFY tipo ENUM('kpis','hallazgos','serie','tabla','ficha','plan','texto','resumen_cliente') NOT NULL");
    }

    public function down(): void
    {
        // Sin la entrega de cliente ni la sección de resumen no hay valor que los enums viejos acepten.
        DB::table('reporte_secciones')->where('tipo', 'resumen_cliente')->delete();
        DB::statement("ALTER TABLE reporte_secciones MODIFY tipo ENUM('kpis','hallazgos','serie','tabla','ficha','plan','texto') NOT NULL");
        DB::table('reporte_entregas')->where('formato', 'pdf_cliente')->delete();
        DB::statement("ALTER TABLE reporte_entregas MODIFY formato ENUM('pdf','xlsx') NOT NULL");

        Schema::table('reporte_secciones', function (Blueprint $table) {
            $table->dropColumn('precarga');
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
