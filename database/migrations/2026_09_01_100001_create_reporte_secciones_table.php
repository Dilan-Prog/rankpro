<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->onDelete('cascade');
            $table->enum('tipo', ['kpis', 'hallazgos', 'serie', 'tabla', 'ficha', 'plan', 'texto']);
            $table->string('titulo');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->json('contenido')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['reporte_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_secciones');
    }
};
