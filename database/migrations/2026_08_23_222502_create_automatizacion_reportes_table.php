<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatizacion_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('automatizacion_proyectos')->onDelete('cascade');
            $table->unsignedInteger('ciclo');

            $table->unsignedInteger('flujos_activos_total')->nullable();
            $table->decimal('horas_ahorradas_mes', 8, 2)->nullable();
            $table->unsignedInteger('mensajes_gestionados_mes')->nullable();
            $table->unsignedInteger('tareas_automatizadas_mes')->nullable();
            $table->text('incidencias')->nullable();
            $table->text('conclusiones')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->unsignedTinyInteger('satisfaccion_cliente')->nullable();
            $table->boolean('continua_proyecto')->nullable();
            $table->text('notas_cierre')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatizacion_reportes');
    }
};
