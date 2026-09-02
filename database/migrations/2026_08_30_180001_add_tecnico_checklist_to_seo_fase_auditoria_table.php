<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->json('tecnico_checklist')->nullable()->after('checklist');
        });
    }

    public function down(): void
    {
        Schema::table('seo_fase_auditoria', function (Blueprint $table) {
            $table->dropColumn('tecnico_checklist');
        });
    }
};
