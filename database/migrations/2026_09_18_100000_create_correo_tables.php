<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de correo: plantillas, envíos, destinatarios y eventos de apertura/clic.
 *
 * Cuatro tablas con prefijo `correo_`. Decisiones que no son obvias:
 *
 * - `correo_envios.html_congelado` guarda el HTML tal como se mandó. Si después
 *   editan la plantilla, el historial sigue enseñando lo que recibió el cliente.
 * - `correo_envios.plantilla_id` es nullOnDelete: borrar una plantilla no puede
 *   borrar el historial de lo que ya se envió con ella.
 * - `correo_destinatarios.token` identifica al destinatario en el píxel y en
 *   los clics. Es aleatorio y no derivable del email: nadie puede fabricar la
 *   URL de apertura de otro.
 * - Los contadores `aperturas` y `clics` del destinatario son caché derivada de
 *   `correo_eventos`, igual que `posicion_actual` en keywords: el detalle manda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correo_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('categoria', ['reportes', 'facturacion', 'propuestas', 'onboarding', 'otros'])->default('otros');
            $table->enum('estado', ['activa', 'archivada'])->default('activa');
            $table->string('asunto');
            $table->json('bloques')->nullable();
            $table->json('marca')->nullable();
            // Modo HTML libre, excluyente con los bloques: si está, manda él.
            $table->text('html_personalizado')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('correo_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->nullable()->constrained('correo_plantillas')->nullOnDelete();
            $table->string('asunto');
            $table->string('remitente_nombre')->nullable();
            $table->string('remitente_email')->nullable();
            $table->enum('estado', ['borrador', 'programado', 'enviando', 'enviado', 'fallido', 'cancelado'])->default('borrador');
            $table->dateTime('programado_para')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->json('variables')->nullable();
            $table->longText('html_congelado')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'programado_para']);
        });

        Schema::create('correo_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->constrained('correo_envios')->onDelete('cascade');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('email');
            $table->string('nombre')->nullable();
            // Sobrescriben las variables del envío: {{contacto}} es por persona.
            $table->json('variables')->nullable();
            $table->string('token', 48)->unique();
            $table->enum('estado', ['pendiente', 'enviado', 'fallido'])->default('pendiente');
            $table->dateTime('enviado_en')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('primera_apertura_en')->nullable();
            $table->unsignedInteger('aperturas')->default(0);
            $table->unsignedInteger('clics')->default(0);
            $table->timestamps();

            $table->unique(['envio_id', 'email']);
        });

        Schema::create('correo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destinatario_id')->constrained('correo_destinatarios')->onDelete('cascade');
            $table->enum('tipo', ['apertura', 'clic']);
            $table->text('url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['destinatario_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_eventos');
        Schema::dropIfExists('correo_destinatarios');
        Schema::dropIfExists('correo_envios');
        Schema::dropIfExists('correo_plantillas');
    }
};
