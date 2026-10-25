<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila única (id=1) con la llave de la cuenta de servicio de Google: la llave va cifrada
        // y el correo en claro para poder mostrar con qué cuenta hay que dar de alta las propiedades.
        Schema::create('gsc_conexion', function (Blueprint $table) {
            $table->id();
            $table->string('client_email')->nullable();
            $table->text('llave_json')->nullable();
            // sin_llave | pendiente (guardada, sin probar) | ok | error
            $table->string('estado', 20)->default('sin_llave');
            $table->timestamp('ultima_prueba_en')->nullable();
            $table->boolean('ultima_prueba_ok')->nullable();
            $table->string('ultima_prueba_resultado', 400)->nullable();
            // Propiedades que devolvió Google en la última prueba buena; alimentan el selector de la pantalla.
            $table->json('propiedades_detectadas')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_conexion');
    }
};
