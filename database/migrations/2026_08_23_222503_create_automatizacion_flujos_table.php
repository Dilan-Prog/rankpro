<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El registro de cada flujo automatizado individual (construido en n8n,
     * fuera del sistema) — no es cycle-scoped, es un hijo real del proyecto
     * como posiciones/backlinks/contenido en SEO. Alimenta la calculadora
     * de precios (complejidad + integraciones) y las estadísticas de
     * impacto (horas ahorradas, mensajes gestionados).
     */
    public function up(): void
    {
        Schema::create('automatizacion_flujos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('automatizacion_proyectos')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('tipo', ['whatsapp', 'crm', 'email', 'notificaciones', 'otro'])->default('otro');
            $table->enum('complejidad', ['basico', 'intermedio', 'avanzado'])->default('basico');
            $table->json('integraciones')->nullable();
            $table->decimal('horas_ahorradas_mes', 8, 2)->nullable();
            $table->unsignedInteger('mensajes_gestionados_mes')->nullable();
            $table->enum('estado', ['activo', 'pausado', 'inactivo'])->default('activo');
            $table->date('fecha_implementado')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatizacion_flujos');
    }
};
