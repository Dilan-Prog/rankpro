<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_planeacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->text('objetivos')->nullable();
            $table->text('requerimientos_funcionales')->nullable();
            $table->text('requerimientos_tecnicos')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_planeacion');
    }
};
