<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correo_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('correo_envios')->cascadeOnDelete();
            $table->string('nombre', 255);
            $table->string('ruta', 255);
            $table->string('disco', 20)->default('local');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamano');
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_adjuntos');
    }
};
