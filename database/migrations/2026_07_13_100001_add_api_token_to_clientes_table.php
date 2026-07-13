<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable()->unique()->after('notas');
            $table->timestamp('api_token_regenerated_at')->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['api_token', 'api_token_regenerated_at']);
        });
    }
};
