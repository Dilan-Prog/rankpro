<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enlaces del pie del sitio público. `red` es una clave de App\Support\CatalogoRedes
        // (icono y dominios válidos); no es enum de BD para poder sumar redes sin migrar.
        Schema::create('redes_sociales', function (Blueprint $table) {
            $table->id();
            $table->string('red', 30);
            // Texto para lectores de pantalla; si falta se usa el nombre de la red.
            $table->string('nombre', 60)->nullable();
            $table->string('url', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_sociales');
    }
};
