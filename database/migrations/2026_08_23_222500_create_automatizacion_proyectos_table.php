<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatizacion_proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('estado', ['activa', 'pausada', 'finalizada'])->default('activa');
            $table->enum('fase_actual', ['diagnostico', 'diseno_flujo', 'implementacion', 'reporte', 'cerrada'])->default('diagnostico');
            $table->unsignedInteger('ciclo_actual')->default(1);
            $table->text('notas')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatizacion_proyectos');
    }
};
