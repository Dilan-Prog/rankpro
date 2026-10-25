<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_metricas_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('gsc_propiedades')->cascadeOnDelete();
            // Día 1 del mes, igual que metrica_cliente_valores.periodo.
            $table->date('periodo');
            $table->unsignedInteger('clics')->default(0);
            $table->unsignedBigInteger('impresiones')->default(0);
            // Fracción 0..1 (clics / impresiones), no porcentaje.
            $table->decimal('ctr', 8, 6)->default(0);
            $table->decimal('posicion_media', 6, 2)->nullable();
            // Mes en curso: Google aún no cierra los últimos 2-3 días, así que la cifra puede cambiar.
            $table->boolean('parcial')->default(false);
            $table->timestamps();

            $table->unique(['propiedad_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_metricas_mensuales');
    }
};
