<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un valor por métrica y mes (periodo = día 1); el unique evita duplicados si se guarda dos veces.
        Schema::create('metrica_cliente_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metrica_id')->constrained('metricas_cliente')->cascadeOnDelete();
            $table->date('periodo');
            $table->decimal('valor', 14, 2);
            $table->text('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['metrica_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrica_cliente_valores');
    }
};
