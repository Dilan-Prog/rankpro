<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['alta', 'media', 'baja'])->default('media');
            $table->enum('estado', ['pendiente', 'en_progreso', 'completada'])->default('pendiente');
            $table->date('fecha_limite')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
