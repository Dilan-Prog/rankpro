<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_propiedades', function (Blueprint $table) {
            $table->id();
            // Una propiedad por cliente: el cruce con el banco de keywords se hace por cliente.
            $table->foreignId('cliente_id')->unique()->constrained('clientes')->cascadeOnDelete();
            $table->string('site_url');
            $table->boolean('activa')->default(true);
            $table->timestamp('ultima_sync_en')->nullable();
            $table->boolean('ultimo_ok')->nullable();
            $table->string('ultimo_resultado', 400)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_propiedades');
    }
};
