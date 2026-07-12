<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('tipo', ['contrato', 'propuesta', 'diseno', 'reporte', 'otro'])->default('otro');
            $table->string('ruta_archivo');
            $table->unsignedBigInteger('tamano')->nullable();
            $table->string('extension', 10)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};
