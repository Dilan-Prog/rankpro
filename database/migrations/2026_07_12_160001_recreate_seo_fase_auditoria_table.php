<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redesign: Auditoría becomes cycle-scoped (one row per ciclo, like
 * seo_reportes already was) so "Nuevo Ciclo" preserves full history
 * instead of overwriting a single 1:1 row. Also absorbs the audit metrics
 * that used to live directly on seo_campanas (seo_score, velocidad_*,
 * sitemap_ok, robots_ok, errores_tecnicos) plus the full Core Web Vitals /
 * technical SEO field set. 0 real rows exist, so drop+recreate is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seo_fase_auditoria');

        Schema::create('seo_fase_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_campana_id')->constrained('seo_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->decimal('velocidad_mobile', 5, 2)->nullable();
            $table->decimal('velocidad_desktop', 5, 2)->nullable();

            $table->decimal('lcp_mobile', 5, 2)->nullable();
            $table->decimal('fid_mobile', 6, 2)->nullable();
            $table->decimal('cls_mobile', 4, 3)->nullable();
            $table->decimal('lcp_desktop', 5, 2)->nullable();
            $table->decimal('fid_desktop', 6, 2)->nullable();
            $table->decimal('cls_desktop', 4, 3)->nullable();

            $table->unsignedInteger('errores_tecnicos')->nullable();
            $table->boolean('indexacion_ok')->default(false);
            $table->boolean('sitemap_ok')->default(false);
            $table->boolean('robots_ok')->default(false);
            $table->unsignedInteger('errores_404')->nullable();
            $table->unsignedInteger('redirecciones_incorrectas')->nullable();
            $table->boolean('duplicidad_contenido')->default(false);
            $table->boolean('canonical_ok')->default(false);
            $table->boolean('schema_ok')->default(false);
            $table->enum('herramienta', ['semrush', 'ahrefs', 'screaming_frog', 'google_search_console', 'otro'])->nullable();
            $table->text('notas')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_fase_auditoria');
    }
};
