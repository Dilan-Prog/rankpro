<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_contenido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->string('titulo');
            $table->string('keyword_objetivo')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('trafico_generado')->nullable();
            $table->enum('estado', ['borrador', 'publicado', 'actualizar'])->default('borrador');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_contenido');
    }
};
