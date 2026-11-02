<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una sola ficha de identidad por cliente (unique): se crea al primer guardado.
        Schema::create('identidad_marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->unique()->constrained('clientes')->cascadeOnDelete();
            $table->string('logo_ruta')->nullable();
            $table->string('slogan', 200)->nullable();
            $table->json('paleta')->nullable();
            $table->string('fuente_titulos', 60)->nullable();
            $table->string('fuente_texto', 60)->nullable();
            $table->text('voz')->nullable();
            $table->json('tonos')->nullable();
            $table->text('mision')->nullable();
            $table->text('vision')->nullable();
            $table->text('valores')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identidad_marcas');
    }
};
