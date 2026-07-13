<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gclid/gbraid/wbraid are duplicated here (not only reachable via ads_clic_id)
 * so the CSV export never needs a join and survives even if the original
 * click row can't be resolved by visitor_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_conversiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('ads_clic_id')->nullable()->constrained('ads_clics')->onDelete('set null');
            $table->string('visitor_id', 64);
            $table->string('gclid')->nullable();
            $table->string('gbraid')->nullable();
            $table->string('wbraid')->nullable();
            $table->enum('tipo', ['formulario', 'whatsapp', 'llamada', 'compra']);
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('moneda', 3)->default('MXN');
            $table->enum('estado', ['pendiente', 'exportada'])->default('pendiente');
            $table->timestamp('exportada_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('visitor_id');
            $table->index(['cliente_id', 'created_at']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_conversiones');
    }
};
