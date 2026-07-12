<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_creativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->string('titulo');
            $table->text('copy')->nullable();
            $table->enum('tipo', ['imagen', 'video', 'carrusel'])->default('imagen');
            $table->string('url_imagen')->nullable();
            $table->decimal('ctr', 6, 3)->nullable();
            $table->enum('estado', ['activo', 'pausado'])->default('activo');
            $table->boolean('ab_testing')->default(false);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_creativos');
    }
};
