<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_optimizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->date('fecha');
            $table->enum('tipo', ['puja', 'audiencia', 'creativo', 'presupuesto', 'keyword']);
            $table->text('descripcion');
            $table->text('resultado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_optimizaciones');
    }
};
