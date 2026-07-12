<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_campanas', function (Blueprint $table) {
            $table->enum('fase_actual', ['auditoria', 'estrategia', 'ejecucion', 'reporte'])
                ->default('auditoria')->after('estado');
            $table->unsignedInteger('ciclo_actual')->default(1)->after('fase_actual');
        });
    }

    public function down(): void
    {
        Schema::table('seo_campanas', function (Blueprint $table) {
            $table->dropColumn(['fase_actual', 'ciclo_actual']);
        });
    }
};
