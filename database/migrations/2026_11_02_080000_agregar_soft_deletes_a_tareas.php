<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Mi espacio» permite deshacer el borrado de una tarea: se borra de forma suave
 * (deleted_at) y un toast ofrece restaurarla durante unos segundos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
