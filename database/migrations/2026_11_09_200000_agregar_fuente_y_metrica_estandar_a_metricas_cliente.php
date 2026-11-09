<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `fuente` es solo un dato (de dónde sale la cifra); nada se autollena desde ella. El default 'manual'
     * describe fielmente las métricas existentes. El único (cliente, metrica_estandar) hace que la BD sea la
     * verdad contra duplicar una métrica del catálogo; los NULL (personalizadas) no chocan entre sí.
     */
    public function up(): void
    {
        Schema::table('metricas_cliente', function (Blueprint $table) {
            $table->string('fuente', 30)->nullable()->default('manual')->after('sentido');
            $table->string('metrica_estandar', 40)->nullable()->after('fuente');
            $table->unique(['cliente_id', 'metrica_estandar'], 'metricas_cliente_estandar_unica');
        });
    }

    public function down(): void
    {
        Schema::table('metricas_cliente', function (Blueprint $table) {
            // MySQL apoyó la FK de cliente_id en el índice único (soltó el suyo); se repone antes de quitarlo.
            // Si ya quedó uno de una reversión anterior no se repite (down -> up -> down daba "duplicate key").
            $existe = DB::selectOne(
                'select count(*) as n from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
                ['metricas_cliente', 'metricas_cliente_cliente_id_index']
            )->n > 0;
            if (! $existe) {
                $table->index('cliente_id', 'metricas_cliente_cliente_id_index');
            }
            $table->dropUnique('metricas_cliente_estandar_unica');
            $table->dropColumn(['fuente', 'metrica_estandar']);
        });
    }
};
