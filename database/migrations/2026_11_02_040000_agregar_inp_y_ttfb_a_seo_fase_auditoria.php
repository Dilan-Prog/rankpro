<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INP y TTFB de la auditoría SEO. Google reemplazó FID por INP como métrica de
 * interactividad, pero las auditorías ya capturadas guardan FID: se conserva y
 * se añaden estas columnas (nullable) sin tocar nada de lo existente.
 * Ambas se guardan en milisegundos, igual que FID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->decimal('inp_mobile', 7, 2)->nullable()->after('cls_desktop');
            $table->decimal('inp_desktop', 7, 2)->nullable()->after('inp_mobile');
            $table->decimal('ttfb_mobile', 7, 2)->nullable()->after('inp_desktop');
            $table->decimal('ttfb_desktop', 7, 2)->nullable()->after('ttfb_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->dropColumn(['inp_mobile', 'inp_desktop', 'ttfb_mobile', 'ttfb_desktop']);
        });
    }
};
