<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Días puntuales sin disponibilidad (feriados, vacaciones) aunque ese
        // día de la semana tenga horario activo.
        Schema::create('disponibilidad_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_bloqueos');
    }
};
