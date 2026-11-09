<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces de solo lectura a la versión para cliente de un reporte. Se guarda el
 * SHA-256 del token, nunca el token: quien lea la base no puede reconstruir un enlace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_enlaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamp('revocado_en')->nullable();
            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->unsignedInteger('accesos')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reporte_id', 'revocado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_enlaces');
    }
};
