<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_onpage_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->string('url_pagina');
            $table->text('accion');
            $table->date('fecha')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['en_progreso', 'completada', 'pausada'])->default('en_progreso');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_onpage_acciones');
    }
};
