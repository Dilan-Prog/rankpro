<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('seo_campana_id')->nullable()->constrained('seo_campanas')->nullOnDelete();
            $table->string('folio')->nullable()->unique();
            $table->string('titulo')->default('Propuesta de Continuidad SEO');
            $table->enum('estado', ['borrador', 'enviada', 'aprobada', 'rechazada'])->default('borrador');
            $table->decimal('precio_mensual', 10, 2)->nullable();
            $table->unsignedInteger('horas_mensuales')->nullable();
            $table->decimal('tarifa_hora', 8, 2)->nullable();
            $table->date('fecha_emision')->nullable();

            // Una columna JSON por pestaña del editor — ver App\Support\Propuestas\Plantilla::vacio()
            // para la forma exacta sembrada al crear. precio_mensual/horas_mensuales/tarifa_hora y
            // titulo NO se repiten aquí aunque se editan desde la Pestaña 1: se usan también para
            // filtrar/listar en el índice, así que viven como columnas reales.
            $table->json('resumen')->nullable();
            $table->json('situacion_actual')->nullable();
            $table->json('contexto_continuidad')->nullable();
            $table->json('plan_detalle')->nullable();
            $table->json('condiciones_proyeccion')->nullable();

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuestas');
    }
};
