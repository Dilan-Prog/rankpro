<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia del seguimiento público: el snippet genera un evento_id por
 * evento y lo reutiliza en los reintentos. El índice único compuesto
 * (cliente_id, evento_id) hace que la BD decida en una carrera; los NULL no
 * chocan entre sí, así los snippets ya instalados (sin id) siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads_clics', function (Blueprint $table) {
            $table->string('evento_id', 64)->nullable()->after('visitor_id');
            $table->unique(['cliente_id', 'evento_id'], 'ads_clics_cliente_evento_unique');
        });

        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->string('evento_id', 64)->nullable()->after('visitor_id');
            $table->unique(['cliente_id', 'evento_id'], 'ads_conversiones_cliente_evento_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->dropUnique('ads_conversiones_cliente_evento_unique');
            $table->dropColumn('evento_id');
        });

        Schema::table('ads_clics', function (Blueprint $table) {
            $table->dropUnique('ads_clics_cliente_evento_unique');
            $table->dropColumn('evento_id');
        });
    }
};
