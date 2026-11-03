<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La variación de una posición SEO solo existe si hay posición actual Y
 * anterior reales (0 o ausente = "sin ranking"). Antes se calculaba con
 * `?? 0`, lo que inventaba -5 / +10 al faltar un dato. Pasa a NULL y se
 * limpian las filas ya guardadas con esa cifra inventada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE seo_posiciones MODIFY variacion INT NULL DEFAULT NULL');

        DB::table('seo_posiciones')
            ->where(function ($q) {
                $q->whereNull('posicion_actual')->orWhere('posicion_actual', 0)
                    ->orWhereNull('posicion_anterior')->orWhere('posicion_anterior', 0);
            })
            ->update(['variacion' => null]);
    }

    public function down(): void
    {
        DB::table('seo_posiciones')->whereNull('variacion')->update(['variacion' => 0]);
        DB::statement('ALTER TABLE seo_posiciones MODIFY variacion INT NOT NULL DEFAULT 0');
    }
};
