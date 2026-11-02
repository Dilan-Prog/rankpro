<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un bug puede tener un responsable (usuario del panel). Es opcional y
 * nullOnDelete: si se borra al usuario el bug no debe desaparecer, solo
 * queda sin asignar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bugs', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->after('proyecto_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bugs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
    }
};
