<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clave que el servidor entrega al pintar el formulario de /agendar. El
        // indice UNICO es la verdad ante doble clic/reintentos/peticiones
        // simultaneas: la segunda insercion con la misma clave falla en BD y el
        // controlador devuelve la reunion ya creada. Nullable porque las
        // reuniones previas (y las del admin) no tienen clave; MySQL permite
        // varios NULL en un indice unico.
        Schema::table('reuniones', function (Blueprint $table) {
            $table->string('clave_idempotencia', 64)->nullable()->unique()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropUnique(['clave_idempotencia']);
            $table->dropColumn('clave_idempotencia');
        });
    }
};
