<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // Articulo -> servicios del catalogo publico (hub-and-spoke)
        // ----------------------------------------------------------------
        // El "otro lado" NO es una FK: los servicios no viven en base de datos,
        // viven en App\Support\Servicios (array estatico). Se guarda el slug y
        // la integridad la garantiza la validacion del formulario y el comando
        // blog:validar, no la base. Es la contrapartida de tener el catalogo
        // publico fuera de la BD, y es un intercambio consciente.
        Schema::create('articulo_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->string('servicio_slug');
            $table->timestamps();

            $table->unique(['articulo_id', 'servicio_slug']);
            $table->index('servicio_slug');
        });

        // ----------------------------------------------------------------
        // Articulo <-> articulo relacionado (manual)
        // ----------------------------------------------------------------
        // Si un articulo no declara relacionados, el repositorio los deriva del
        // cluster. Esta tabla solo existe para poder forzar relaciones concretas
        // cuando la derivacion automatica no acierta.
        Schema::create('articulo_relacionado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignId('relacionado_id')->constrained('articulos')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['articulo_id', 'relacionado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_relacionado');
        Schema::dropIfExists('articulo_servicio');
    }
};
