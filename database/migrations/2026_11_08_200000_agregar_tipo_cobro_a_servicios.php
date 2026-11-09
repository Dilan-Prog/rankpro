<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El default 'mensual' conserva el comportamiento actual: por sí sola la migración no cambia
     * ninguna cifra; la reclasificación de lo existente es el comando servicios:clasificar-cobro.
     */
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->string('tipo_cobro', 20)->default('mensual')->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('tipo_cobro');
        });
    }
};
