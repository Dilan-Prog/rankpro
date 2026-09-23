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
        // Una fila por día de la semana (0=domingo..6=sábado, único): el rango
        // horario en que se puede agendar ese día, si `activo`.
        Schema::create('disponibilidad_horarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dia_semana')->unique();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_horarios');
    }
};
