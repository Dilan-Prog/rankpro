<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->enum('tipo', ['seo', 'google_ads', 'meta_ads', 'tiktok_ads', 'rediseno', 'software']);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_mensual', 12, 2)->default(0);
            $table->enum('estado', ['activo', 'pausado', 'cancelado'])->default('activo');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
