<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('empresa')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->enum('estado', ['activo', 'pausado', 'cancelado'])->default('activo');
            $table->date('fecha_inicio_contrato')->nullable();
            $table->date('fecha_renovacion_contrato')->nullable();
            $table->enum('forma_pago', ['mensual', 'trimestral', 'anual'])->nullable();
            $table->enum('metodo_pago', ['transferencia', 'tarjeta', 'efectivo', 'paypal'])->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
