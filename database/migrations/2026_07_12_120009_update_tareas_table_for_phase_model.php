<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tareas.cliente_id was redundant (always derivable via proyecto->cliente)
 * and isn't part of the new phase-based Tarea shape, which instead needs a
 * free-text `responsable`. Safe to drop outright — 0 rows exist at the time
 * this module was rebuilt for the Munch Galindo process.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
            $table->string('responsable')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn('responsable');
            $table->foreignId('cliente_id')->nullable()->after('proyecto_id')->constrained('clientes')->onDelete('cascade');
        });
    }
};
