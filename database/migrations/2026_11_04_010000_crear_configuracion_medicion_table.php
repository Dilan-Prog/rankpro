<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila única (id=1, ver App\Models\ConfiguracionMedicion::actual()): el dueño pega
        // los códigos de Google desde el panel y el sitio público los carga sin tocar el
        // .env (el servidor tiene config en caché y APP_ENV=local, por eso el .env no sirve).
        // Si no hay fila, Medicion cae al comportamiento anterior (config/analytics.php).
        Schema::create('configuracion_medicion', function (Blueprint $table) {
            $table->id();
            $table->string('gtm_id', 20)->nullable();
            $table->string('ga4_id', 20)->nullable();
            $table->string('google_ads_id', 20)->nullable();
            // Etiquetas de conversión de Google Ads: AW-123456789/AbC_dEfGhIj
            $table->string('conversion_agendar', 80)->nullable();
            $table->string('conversion_whatsapp', 80)->nullable();
            $table->string('conversion_telefono', 80)->nullable();
            $table->boolean('activa')->default(false);
            // Hosts donde SÍ se emite (coma o salto de línea): evita medir staging o copias.
            $table->string('dominios', 255)->default('rankprosolutions.com.mx');
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_medicion');
    }
};
