<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->enum('area', ['seo', 'ads', 'web']);
            $table->foreignId('seo_campana_id')->nullable()->constrained('seo_campanas')->nullOnDelete();
            $table->foreignId('ads_campana_id')->nullable()->constrained('ads_campanas')->nullOnDelete();
            $table->string('titulo');
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->date('fecha_emision')->nullable();
            $table->enum('estado', ['borrador', 'listo', 'entregado'])->default('borrador');
            $table->string('numero')->nullable()->unique();
            $table->text('notas_alcance')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
