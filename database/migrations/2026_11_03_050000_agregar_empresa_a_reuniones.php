<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El formulario público /agendar pregunta por "Empresa o sitio web" pero
        // no se guardaba en ningún lado. Nullable: es opcional y las reuniones
        // previas (y las del admin) no la traen.
        Schema::table('reuniones', function (Blueprint $table) {
            $table->string('empresa', 255)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn('empresa');
        });
    }
};
