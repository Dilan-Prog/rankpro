<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha en que se hizo la auditoría que respalda el SEO Score. `updated_at` no sirve: cambia con
     * cualquier edición de la fase y mostraría un score viejo como fresco. Las filas anteriores se quedan
     * en null a propósito (no se conoce su fecha real y inventarla sería peor que decir "sin fecha").
     */
    public function up(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->date('fecha_auditoria')->nullable()->after('seo_score');
        });
    }

    public function down(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->dropColumn('fecha_auditoria');
        });
    }
};
