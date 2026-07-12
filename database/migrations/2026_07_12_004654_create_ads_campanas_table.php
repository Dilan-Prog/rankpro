<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_campanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('plataforma', ['google_ads', 'meta_ads', 'tiktok_ads']);
            $table->enum('objetivo', ['leads', 'ventas', 'trafico', 'branding']);
            $table->decimal('presupuesto_mensual', 12, 2)->default(0);
            $table->enum('estado', ['activa', 'pausada', 'finalizada'])->default('activa');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_campanas');
    }
};
