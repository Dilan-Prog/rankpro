<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces mágicos del portal de solo lectura del cliente final. Solo se guarda el SHA-256 del
 * token (nunca el token): quien lea la base no puede reconstruir un enlace. `etiqueta` y `correo`
 * son informativos (a quién se le dio) y no autentican nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('etiqueta', 120)->nullable();
            $table->string('correo', 190)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamp('revocado_en')->nullable();
            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->unsignedInteger('accesos')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cliente_id', 'revocado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_accesos');
    }
};
