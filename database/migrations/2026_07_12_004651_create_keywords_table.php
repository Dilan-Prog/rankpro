<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('campana_id')->nullable()->constrained('seo_campanas')->nullOnDelete();
            $table->string('keyword');
            $table->enum('tipo', ['principal', 'secundaria', 'long_tail', 'lsi'])->default('secundaria');
            $table->unsignedInteger('volumen_busqueda')->nullable();
            $table->unsignedTinyInteger('dificultad')->nullable();
            $table->decimal('cpc_estimado', 8, 2)->nullable();
            $table->enum('intencion', ['informacional', 'transaccional', 'navegacional'])->nullable();
            $table->string('idioma')->default('es');
            $table->string('pais')->nullable();
            $table->enum('herramienta_origen', ['semrush', 'ahrefs', 'google_kp', 'otro'])->nullable();
            $table->string('url_asignada')->nullable();
            $table->unsignedInteger('posicion_actual')->nullable();
            $table->enum('estado', ['en_uso', 'seguimiento', 'descartada'])->default('seguimiento');
            $table->date('fecha_incorporacion')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
