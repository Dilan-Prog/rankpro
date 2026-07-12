<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['alta', 'media', 'baja'])->default('media');
            $table->enum('estado', ['abierto', 'en_progreso', 'resuelto'])->default('abierto');
            $table->date('fecha_resolucion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bugs');
    }
};
