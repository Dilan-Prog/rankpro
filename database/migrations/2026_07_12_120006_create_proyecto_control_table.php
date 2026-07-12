<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_control', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('url_produccion')->nullable();
            $table->boolean('credenciales_entregadas')->default(false);
            $table->boolean('manual_entregado')->default(false);
            $table->boolean('capacitacion_realizada')->default(false);
            $table->boolean('pago_final_recibido')->default(false);
            $table->decimal('monto_pago_final', 12, 2)->default(0);
            $table->date('fecha_entrega_real')->nullable();
            $table->unsignedTinyInteger('satisfaccion_cliente')->nullable();
            $table->text('notas_cierre')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_control');
    }
};
