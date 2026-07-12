<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('tipo');
            $table->enum('fase_actual', ['planeacion', 'organizacion', 'direccion', 'control', 'cerrado'])
                ->default('planeacion')->after('descripcion');
            $table->unsignedTinyInteger('porcentaje_avance')->default(0)->after('fase_actual');
            $table->decimal('anticipo', 12, 2)->default(0)->after('presupuesto');
            $table->enum('forma_pago', ['mensual', 'etapas', 'unico'])->nullable()->after('pagos_recibidos');
            $table->string('responsable')->nullable()->after('forma_pago');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'fase_actual', 'porcentaje_avance', 'anticipo', 'forma_pago', 'responsable']);
        });
    }
};
