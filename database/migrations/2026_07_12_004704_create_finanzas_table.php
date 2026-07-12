<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finanzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->string('concepto');
            $table->enum('tipo', ['ingreso', 'gasto']);
            $table->decimal('monto', 12, 2);
            $table->enum('estado', ['pagado', 'pendiente', 'vencido'])->default('pendiente');
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finanzas');
    }
};
