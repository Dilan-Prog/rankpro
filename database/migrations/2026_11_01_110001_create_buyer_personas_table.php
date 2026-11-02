<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->text('perfil')->nullable();
            $table->text('dolores')->nullable();
            $table->text('mensaje')->nullable();
            $table->string('canales', 255)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['cliente_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_personas');
    }
};
