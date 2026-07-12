<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These now live on seo_fase_auditoria, scoped per cycle — keeping them
 * here too would create two sources of truth once a campaign starts its
 * second cycle. seo_campanas keeps only campaign-level identity data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_campanas', function (Blueprint $table) {
            $table->dropColumn([
                'seo_score', 'trafico_organico_mensual', 'backlinks_total',
                'errores_tecnicos', 'velocidad_mobile', 'velocidad_desktop',
                'sitemap_ok', 'robots_ok',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('seo_campanas', function (Blueprint $table) {
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedInteger('trafico_organico_mensual')->nullable();
            $table->unsignedInteger('backlinks_total')->nullable();
            $table->unsignedInteger('errores_tecnicos')->nullable();
            $table->decimal('velocidad_mobile', 5, 2)->nullable();
            $table->decimal('velocidad_desktop', 5, 2)->nullable();
            $table->boolean('sitemap_ok')->default(false);
            $table->boolean('robots_ok')->default(false);
        });
    }
};
