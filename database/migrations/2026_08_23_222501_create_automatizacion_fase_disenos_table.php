<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatizacion_fase_disenos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('automatizacion_proyectos')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->text('flujos_planeados')->nullable();
            $table->string('integraciones_planeadas')->nullable();
            $table->string('diagrama_url')->nullable();
            $table->text('cronograma')->nullable();
            $table->text('notas')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatizacion_fase_disenos');
    }
};
