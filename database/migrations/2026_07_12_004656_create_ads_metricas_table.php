<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_metricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->decimal('inversion_real', 12, 2)->default(0);
            $table->unsignedBigInteger('impresiones')->default(0);
            $table->unsignedInteger('clics')->default(0);
            $table->decimal('ctr', 6, 3)->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->unsignedInteger('conversiones')->default(0);
            $table->decimal('cpl', 8, 2)->nullable();
            $table->decimal('cpa', 8, 2)->nullable();
            $table->decimal('roas', 6, 2)->nullable();
            $table->decimal('valor_conversion', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['ads_campana_id', 'mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_metricas');
    }
};
