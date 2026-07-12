<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_direccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->unsignedTinyInteger('porcentaje_avance')->default(0);
            $table->decimal('pagos_recibidos_fase', 12, 2)->default(0);
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_direccion');
    }
};
