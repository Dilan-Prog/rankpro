<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->onDelete('cascade');
            $table->string('evento', 80);
            $table->uuid('uuid')->unique();
            $table->json('payload');
            $table->enum('estado', ['pendiente', 'entregado', 'fallido'])->default('pendiente');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->unsignedSmallInteger('codigo_http')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('proximo_intento_en')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'proximo_intento_en']);
            $table->index(['webhook_id', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_entregas');
    }
};
