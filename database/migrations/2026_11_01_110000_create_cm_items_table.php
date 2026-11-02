<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Árbol de contenido por cliente (módulo «Contenido»): carpetas, documentos
 * Markdown, tablas editables (JSON) y archivos subidos.
 *
 * Un solo árbol con auto-referencia en vez de una tabla por tipo: mover o borrar
 * una carpeta es la misma operación sea cual sea lo que contiene. La pertenencia
 * al cliente se repite en cada fila (no solo en la raíz) para poder listar y
 * buscar por cliente sin recorrer el árbol; el controlador impide que un
 * elemento cuelgue de un padre de otro cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cm_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cm_items')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('nombre');
            // Markdown (documento) o JSON de columnas/filas (tabla); null en carpetas y archivos.
            $table->longText('contenido')->nullable();
            $table->string('archivo_path')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cm_items');
    }
};
