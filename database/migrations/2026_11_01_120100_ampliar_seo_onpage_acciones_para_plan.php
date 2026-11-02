<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las acciones on-page del módulo SEO pasan a ser también las «actividades»
 * del SEO Plan. Todo lo nuevo es nullable (o tiene default) para que el módulo
 * SEO actual siga guardando acciones sin saber que existen estas columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_actividad_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_onpage_accion_id')->constrained('seo_onpage_acciones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota');
            $table->timestamps();
        });

        Schema::table('seo_onpage_acciones', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable()->after('seo_campana_id');
            $table->foreignId('cluster_id')->nullable()->after('tipo')
                ->constrained('keyword_clusters')->nullOnDelete();
            $table->date('fecha_limite')->nullable()->after('fecha');
            $table->date('cerrada_en')->nullable()->after('fecha_limite');
            // Texto y no número: lo medido depende del tipo («posición 12», «3 backlinks», «LCP 3,1 s»).
            $table->string('medicion_antes', 120)->nullable();
            $table->string('medicion_despues', 120)->nullable();
            // Posición dentro de su columna del kanban; el orden manual persiste.
            $table->unsignedInteger('orden')->default(0);
        });

        // El kanban tiene «Pendiente» y «En revisión», que el enum original no conocía.
        // MODIFY es solo de MySQL; otros motores (SQLite) guardan el enum como texto sin restricción.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE seo_onpage_acciones MODIFY estado ENUM('pendiente','en_progreso','en_revision','completada','pausada') NOT NULL DEFAULT 'en_progreso'");
        }
    }

    public function down(): void
    {
        DB::table('seo_onpage_acciones')->whereIn('estado', ['pendiente', 'en_revision'])->update(['estado' => 'en_progreso']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE seo_onpage_acciones MODIFY estado ENUM('en_progreso','completada','pausada') NOT NULL DEFAULT 'en_progreso'");
        }

        Schema::table('seo_onpage_acciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cluster_id');
            $table->dropColumn(['tipo', 'fecha_limite', 'cerrada_en', 'medicion_antes', 'medicion_despues', 'orden']);
        });

        Schema::dropIfExists('seo_actividad_notas');
    }
};
