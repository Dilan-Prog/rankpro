<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_campanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->string('nombre');
            $table->string('url_sitio')->nullable();
            $table->enum('estado', ['activa', 'pausada', 'finalizada'])->default('activa');
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedInteger('trafico_organico_mensual')->nullable();
            $table->unsignedInteger('backlinks_total')->nullable();
            $table->unsignedInteger('errores_tecnicos')->nullable();
            $table->decimal('velocidad_mobile', 5, 2)->nullable();
            $table->decimal('velocidad_desktop', 5, 2)->nullable();
            $table->boolean('sitemap_ok')->default(false);
            $table->boolean('robots_ok')->default(false);
            $table->text('notas')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_campanas');
    }
};
