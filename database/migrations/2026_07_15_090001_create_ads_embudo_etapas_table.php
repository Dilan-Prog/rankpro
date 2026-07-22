<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas del embudo de ventas, personalizables por cliente (no globales) —
 * cada cliente define las suyas (ej. Lead/Prospecto, Lead Calificado, Venta)
 * para clasificar sus conversiones. Todo vive dentro del sistema, sin
 * webhooks externos: la clasificación es un menú por conversión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_embudo_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->foreignId('ads_embudo_etapa_id')->nullable()->after('estado')->constrained('ads_embudo_etapas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ads_embudo_etapa_id');
        });

        Schema::dropIfExists('ads_embudo_etapas');
    }
};
