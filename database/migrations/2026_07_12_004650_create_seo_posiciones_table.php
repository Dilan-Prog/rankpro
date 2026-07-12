<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_posiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('keyword');
            $table->string('url_pagina')->nullable();
            $table->unsignedInteger('posicion_actual')->nullable();
            $table->unsignedInteger('posicion_anterior')->nullable();
            $table->integer('variacion')->default(0);
            $table->unsignedInteger('volumen_busqueda')->nullable();
            $table->unsignedTinyInteger('dificultad_keyword')->nullable();
            $table->enum('dispositivo', ['mobile', 'desktop'])->default('mobile');
            $table->string('pais')->nullable();
            $table->date('fecha_registro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_posiciones');
    }
};
