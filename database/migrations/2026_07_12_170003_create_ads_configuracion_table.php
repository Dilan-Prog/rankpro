<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_configuracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_campana_id')->constrained('ads_campanas')->onDelete('cascade');
            $table->unsignedInteger('ciclo')->default(1);

            $table->text('estructura_campana')->nullable();
            $table->boolean('pixel_ok')->default(false);
            $table->string('cuenta_publicitaria')->nullable();
            $table->boolean('utms_ok')->default(false);
            $table->text('notas')->nullable();

            $table->json('checklist')->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_configuracion');
    }
};
