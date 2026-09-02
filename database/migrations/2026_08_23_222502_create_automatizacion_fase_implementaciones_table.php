<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatizacion_fase_implementaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('automatizacion_proyectos')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->unsignedTinyInteger('porcentaje_avance')->default(0);
            $table->unsignedInteger('flujos_construidos')->default(0);
            $table->boolean('pruebas_realizadas')->default(false);
            $table->boolean('cliente_capacitado')->default(false);
            $table->text('notas')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatizacion_fase_implementaciones');
    }
};
