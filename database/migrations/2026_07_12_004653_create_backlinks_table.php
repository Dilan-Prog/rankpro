<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('url_origen');
            $table->string('url_destino');
            $table->unsignedTinyInteger('da_dr')->nullable();
            $table->enum('tipo', ['dofollow', 'nofollow'])->default('dofollow');
            $table->enum('estado', ['activo', 'caido'])->default('activo');
            $table->date('fecha_conseguido')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlinks');
    }
};
