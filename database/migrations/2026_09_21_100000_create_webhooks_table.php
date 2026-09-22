<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks salientes: RankPro avisa a n8n (u otro receptor) cuando pasa algo.
 * Sin colas (el hosting no tiene worker): el envío real ocurre en
 * App\Services\Webhooks\Despachador dentro de app()->terminating(), y lo que
 * falla queda en webhook_entregas para que webhooks:reintentar (scheduler,
 * cada minuto) lo reintente con backoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('url', 500);
            // Nunca se muestra completo tras crearlo, igual que los tokens de API y el secreto de correo.
            $table->string('secreto', 128);
            $table->json('eventos');
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultimo_disparo_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
