<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Una reunión agendada desde /agendar. `cliente_id` se vincula solo si
        // el email coincide con un Cliente existente; si no, la reunión igual
        // vive con nombre/email/teléfono propios. `token` identifica el enlace
        // público de cancelación (mismo patrón que correo_destinatarios.token).
        Schema::create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nombre');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->text('notas')->nullable();
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('estado')->default('confirmada');
            $table->string('token', 48)->unique();
            $table->timestamps();

            $table->index('inicia_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reuniones');
    }
};
