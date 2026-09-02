<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La versión de una entrega se reserva en una transacción corta que libera el
 * lock antes de renderizar, así que dos generaciones simultáneas del mismo
 * reporte y formato podrían llegar a reservar el mismo número. El índice único
 * cierra esa ventana en la base de datos: la segunda falla al insertar en vez
 * de sobrescribir el fichero de la primera, que es exactamente el problema que
 * el versionado venía a resolver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->unique(['reporte_id', 'formato', 'version'], 'reporte_entregas_version_unica');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_entregas', function (Blueprint $table) {
            $table->dropUnique('reporte_entregas_version_unica');
        });
    }
};
