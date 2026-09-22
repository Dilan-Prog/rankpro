<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fila única (id=1, ver App\Models\ConfiguracionSmtp::actual()): así el
        // panel puede sobreescribir en caliente los MAIL_* del .env sin tocar
        // el servidor. `activa` es el interruptor: si es false (o no hay fila),
        // se usa el .env tal cual, como hasta ahora.
        Schema::create('configuracion_smtp', function (Blueprint $table) {
            $table->id();
            $table->boolean('activa')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('puerto')->nullable();
            $table->string('cifrado', 10)->nullable(); // tls | ssl | null
            $table->string('usuario')->nullable();
            $table->text('password')->nullable(); // cast 'encrypted' en el modelo
            $table->string('remitente_email')->nullable();
            $table->string('remitente_nombre')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_smtp');
    }
};
