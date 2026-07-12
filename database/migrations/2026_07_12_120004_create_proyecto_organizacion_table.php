<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_organizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('stack_tecnologico')->nullable();
            $table->text('arquitectura')->nullable();
            $table->string('herramientas')->nullable();
            $table->string('url_repositorio')->nullable();
            $table->string('url_staging')->nullable();
            $table->json('equipo')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_organizacion');
    }
};
