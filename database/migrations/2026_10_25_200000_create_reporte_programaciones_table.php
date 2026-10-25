<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            // El área decide QUÉ reporte del periodo anterior se manda (el del cliente en esa área).
            $table->string('area', 20);
            $table->foreignId('plantilla_id')->nullable()->constrained('correo_plantillas')->nullOnDelete();
            $table->string('asunto');
            // JSON de emails: lista corta y siempre se lee entera, no justifica tabla aparte.
            $table->json('destinatarios');
            // Tope 28 para que el día exista en todos los meses (sin lógica de fin de mes).
            $table->unsignedTinyInteger('dia_mes');
            $table->time('hora');
            $table->boolean('activo')->default(true);
            $table->dateTime('proximo_envio_en')->nullable();
            $table->dateTime('ultimo_envio_en')->nullable();
            // Resultado persistente (no toast): texto "ok" o el error real, y un bool para pintarlo sin comparar texto.
            $table->boolean('ultimo_ok')->nullable();
            $table->text('ultimo_resultado')->nullable();
            $table->dateTime('ultimo_intento_en')->nullable();
            // La prueba a mi correo no toca el último envío, pero su fallo tampoco debe perderse en un toast.
            $table->text('ultima_prueba')->nullable();
            // Evita reenviar el mismo reporte si el comando corre dos veces el mismo mes.
            $table->foreignId('ultimo_reporte_id')->nullable()->constrained('reportes')->nullOnDelete();
            $table->foreignId('ultimo_correo_envio_id')->nullable()->constrained('correo_envios')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activo', 'proximo_envio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_programaciones');
    }
};
