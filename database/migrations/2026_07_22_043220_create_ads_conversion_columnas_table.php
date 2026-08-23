<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ads_conversion_columnas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->json('datos_personalizados')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads_conversiones', function (Blueprint $table) {
            $table->dropColumn('datos_personalizados');
        });

        Schema::dropIfExists('ads_conversion_columnas');
    }
};
