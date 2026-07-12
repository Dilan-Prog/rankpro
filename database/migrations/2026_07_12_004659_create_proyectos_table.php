<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('tipo', ['rediseno', 'web_nueva', 'software', 'landing']);
            $table->json('tecnologias')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_entrega_estimada')->nullable();
            $table->date('fecha_entrega_real')->nullable();
            $table->decimal('presupuesto', 12, 2)->default(0);
            $table->decimal('pagos_recibidos', 12, 2)->default(0);
            $table->enum('estado', ['briefing', 'disenio', 'frontend', 'backend', 'qa', 'lanzamiento', 'mantenimiento'])->default('briefing');
            $table->string('url_repositorio')->nullable();
            $table->string('url_staging')->nullable();
            $table->string('url_produccion')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
