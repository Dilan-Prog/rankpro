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
        // Fila única (id=1, ver App\Models\AgendaConfiguracion::actual()), mismo
        // patrón que configuracion_smtp: un interruptor `activa` y los parámetros
        // que usa CalculadorDisponibilidad para generar los huecos agendables.
        Schema::create('agenda_configuracion', function (Blueprint $table) {
            $table->id();
            $table->boolean('activa')->default(false);
            $table->unsignedSmallInteger('duracion_minutos')->default(30);
            $table->unsignedSmallInteger('anticipacion_minima_horas')->default(4);
            $table->unsignedSmallInteger('dias_visibles')->default(15);
            $table->string('notificar_email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_configuracion');
    }
};
