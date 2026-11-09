<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los IDs de la plataforma permiten enlazar una campaña con su cuenta/campaña real (hoy solo coincide por nombre).
     * El default 'MXN' conserva la moneda implícita con la que se capturó todo el presupuesto hasta ahora.
     */
    public function up(): void
    {
        Schema::table('ads_campanas', function (Blueprint $table) {
            $table->string('plataforma_cuenta_id', 40)->nullable()->after('plataforma');
            $table->string('plataforma_campana_id', 40)->nullable()->after('plataforma_cuenta_id');
            $table->string('moneda', 3)->default('MXN')->after('presupuesto_mensual');
        });
    }

    public function down(): void
    {
        Schema::table('ads_campanas', function (Blueprint $table) {
            $table->dropColumn(['plataforma_cuenta_id', 'plataforma_campana_id', 'moneda']);
        });
    }
};
