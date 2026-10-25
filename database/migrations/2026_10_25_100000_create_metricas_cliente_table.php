<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Definición de una métrica capturada a mano por cliente; la meta y el
        // sentido viven aquí para que el semáforo se calcule sin guardar estados.
        Schema::create('metricas_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('unidad', 20)->nullable();
            $table->decimal('meta', 14, 2)->nullable();
            $table->string('sentido')->default('mayor');
            $table->unsignedTinyInteger('umbral_amarillo_pct')->default(80);
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_cliente');
    }
};
