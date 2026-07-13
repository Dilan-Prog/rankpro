<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cumulative, not cycle-scoped — ad groups persist across the campaign's cycles like ads_creativos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->string('nombre');
            $table->string('audiencia')->nullable();
            $table->decimal('presupuesto', 12, 2)->default(0);
            $table->json('keywords')->nullable();
            $table->enum('estado', ['activo', 'pausado'])->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_grupos');
    }
};
