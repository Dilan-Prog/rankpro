<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de accesos al portal. No guarda la IP en claro (solo un hash con sal) ni el token
 * (la ruta se guarda sin él): la tabla sirve para saber quién entró y qué abrió, no para rastrear personas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_acceso_id')->constrained('portal_accesos')->cascadeOnDelete();
            $table->string('ruta', 190);
            $table->char('ip_hash', 64);
            $table->string('agente', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['portal_acceso_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_visitas');
    }
};
