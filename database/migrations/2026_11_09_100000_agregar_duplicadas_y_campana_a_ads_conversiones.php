<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un doble clic en WhatsApp generaba dos conversiones (el evento_id es nuevo en cada
 * llamada, así que el índice único no las detiene). Las repetidas se MARCAN apuntando
 * a la primera, nunca se borran: el dato original queda y la marca se puede revertir.
 *
 * ads_campana_id se copia del clic al registrar para no depender de un join en cada lista.
 * Sin backfill masivo a propósito: en producción casi ningún clic tiene campaña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->foreignId('duplicada_de_id')->nullable()->after('ads_clic_id')
                ->constrained('ads_conversiones')->nullOnDelete();
            $table->timestamp('duplicada_at')->nullable()->after('duplicada_de_id');
            $table->foreignId('ads_campana_id')->nullable()->after('duplicada_at')
                ->constrained('ads_campanas')->nullOnDelete();

            // Búsqueda de la "original" al registrar y al deduplicar: mismo cliente, tipo y visitante en orden de tiempo.
            $table->index(['cliente_id', 'tipo', 'visitor_id', 'created_at'], 'ads_conv_grupo_tiempo_index');
        });
    }

    public function down(): void
    {
        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->dropIndex('ads_conv_grupo_tiempo_index');
            $table->dropConstrainedForeignId('ads_campana_id');
            $table->dropConstrainedForeignId('duplicada_de_id');
            $table->dropColumn('duplicada_at');
        });
    }
};
