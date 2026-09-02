<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->foreignId('lista_id')->nullable()->after('campana_id')->constrained('keyword_listas')->nullOnDelete();
            $table->unsignedInteger('posicion_anterior')->nullable()->after('posicion_actual');
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lista_id');
            $table->dropColumn('posicion_anterior');
        });
    }
};
