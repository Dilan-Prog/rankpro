<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Plan: tablas nuevas y aditivas. No se duplica nada de lo que ya existe
 * (campañas, acciones on-page, keywords, métricas): aquí solo van el cuadro
 * de metas por cliente, los clústeres y la bitácora de cada actividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Una fila por cliente: la meta general es texto libre; las metas medibles
        // viven en metricas_cliente para no tener dos fuentes de verdad.
        Schema::create('seo_planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->unique()->constrained('clientes')->cascadeOnDelete();
            $table->text('meta_general')->nullable();
            $table->timestamps();
        });

        Schema::create('keyword_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('url')->nullable();
            $table->timestamps();
        });

        // nullOnDelete: borrar un clúster no debe borrar las keywords del banco, solo soltarlas.
        Schema::table('keywords', function (Blueprint $table) {
            $table->foreignId('cluster_id')->nullable()->after('lista_id')
                ->constrained('keyword_clusters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cluster_id');
        });
        Schema::dropIfExists('keyword_clusters');
        Schema::dropIfExists('seo_planes');
    }
};
